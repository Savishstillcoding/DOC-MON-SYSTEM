<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Users: everyone with a DOC-MON account, with search, tabs and an organization filter.
 * The admin can edit details and deactivate/reactivate accounts. There is no
 * "Add User": everyone joins through sign-up and the ID check (decided 2026-11-01).
 * Role and password are never edited here.
 */
class UserController extends Controller
{
    /** Tab label => role shown (null = everyone). */
    private const TABS = ['All' => null, 'Student Officers' => 'officer', 'Signatories' => 'signatory', 'Admins' => 'admin'];

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $orgId = (int) $request->query('org');

        $base = fn () => User::query()
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('login_id', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            // Officers belong to an organization directly; signatories through its route
            ->when($orgId > 0, fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->where('organization_id', $orgId)
                ->orWhereHas('signatoryOrganizations', fn (Builder $o) => $o->where('organizations.id', $orgId))));

        $counts = collect(self::TABS)->map(fn ($role) => $base()->when($role, fn ($q) => $q->where('role', $role))->count());
        $tab = array_key_exists($request->query('tab'), self::TABS) ? $request->query('tab') : 'All';

        $users = $base()
            ->when(self::TABS[$tab], fn ($q, $role) => $q->where('role', $role))
            ->with(['organization', 'signatoryOrganizations'])
            ->orderBy('last_name')->orderBy('first_name')
            ->paginate(8)
            ->withQueryString();

        return view('admin.users', [
            'users' => $users,
            'counts' => $counts,
            'tab' => $tab,
            'search' => $search,
            'orgId' => $orgId,
            'organizations' => Organization::orderBy('code')->get(['id', 'code']),
        ]);
    }

    public function edit(User $user): View
    {
        return view('admin.user-edit', [
            'account' => $user,
            'organizations' => Organization::orderBy('code')->get(['id', 'code', 'name']),
            'positions' => User::OFFICER_POSITIONS,
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $rules = [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'login_id' => ['required', 'string', 'max:30', Rule::unique('users')->ignore($user->id)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'phone' => ['nullable', 'regex:/^09\d{9}$/'],
        ];

        // Officers: one of the two positions and an organization. Signatories and
        // admins: a free-text title (a signatory's step title is set on Signatory Lists).
        $rules += $user->role === 'officer'
            ? ['position' => ['required', Rule::in(User::OFFICER_POSITIONS)], 'organization_id' => ['required', Rule::exists('organizations', 'id')]]
            : ['position' => ['nullable', 'string', 'max:100']];

        // Only these fields are read: role, status and password can't be changed here
        $data = $request->validate($rules, [
            'login_id.unique' => 'Another account already uses this ID number.',
            'email.unique' => 'Another account already uses this email.',
            'phone.regex' => 'Enter an 11-digit number starting with 09.',
        ]);

        $user->fill($data);
        $changed = array_keys($user->getDirty());
        $user->save();

        if ($changed) {
            ActivityLog::record($request->user(), 'accounts', 'Edited account',
                "{$user->displayName()} · changed ".implode(', ', $changed));
        }

        return redirect('/admin/users')->with('status', "Saved {$user->displayName()}.");
    }

    public function deactivate(Request $request, User $user): RedirectResponse
    {
        // Each rule says WHY, so the admin knows what to do instead
        $problem = match (true) {
            $user->is($request->user()) => 'You cannot deactivate your own account.',
            $user->role === 'admin' => 'Admin accounts cannot be deactivated here.',
            $user->status !== 'verified' => 'Only verified accounts can be deactivated.',
            $user->role === 'signatory' && $user->signatoryOrganizations()->exists() => "{$user->displayName()} is on an approval route ("
                .$user->signatoryOrganizations->pluck('code')->implode(', ')
                .'). Replace them on Signatory Lists first, so no papers get stuck with them.',
            default => null,
        };

        if ($problem) {
            return back()->withErrors(['user' => $problem]);
        }

        DB::transaction(function () use ($user, $request) {
            $user->forceFill(['status' => 'deactivated'])->save();
            ActivityLog::record($request->user(), 'accounts', 'Deactivated account', "{$user->displayName()} ({$user->roleLabel()})");
            // Signed out everywhere right away, not only at their next log-in
            DB::table('sessions')->where('user_id', $user->id)->delete();
        });

        return back()->with('status', "Deactivated {$user->displayName()}. They can no longer log in.");
    }

    public function reactivate(Request $request, User $user): RedirectResponse
    {
        if ($user->status !== 'deactivated') {
            return back()->withErrors(['user' => 'Only deactivated accounts can be reactivated.']);
        }

        $user->forceFill(['status' => 'verified'])->save();
        ActivityLog::record($request->user(), 'accounts', 'Reactivated account', "{$user->displayName()} ({$user->roleLabel()})");

        return back()->with('status', "Reactivated {$user->displayName()}. They can log in again.");
    }
}
