<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Document;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Organizations (AUD-053): the student organizations using DOC-MON. The admin adds
 * and renames them; the approval route itself is set on Signatory Lists.
 * Delete only when the organization is empty (decided 2026-11-01), so no officer,
 * ticket or route is ever lost.
 */
class OrganizationController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));

        $organizations = $this->withNumbers(Organization::query())
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->where('code', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%")))
            ->orderBy('code')
            ->get();

        return view('admin.organizations', [
            'organizations' => $organizations,
            'search' => $search,
            // The first organization ever added is the pilot (DIGITS)
            'pilotId' => Organization::orderBy('id')->value('id'),
        ]);
    }

    public function create(): View
    {
        return view('admin.organization-form', ['organization' => new Organization]);
    }

    public function store(Request $request): RedirectResponse
    {
        $organization = Organization::create($this->validated($request));
        ActivityLog::record($request->user(), 'organizations', 'Added organization', "{$organization->code} · {$organization->name}");

        return redirect('/admin/signatory-lists?org='.$organization->id)
            ->with('status', "Added {$organization->code}. Next: set up its approval route below.");
    }

    public function edit(Organization $organization): View
    {
        return view('admin.organization-form', [
            'organization' => $this->withNumbers(Organization::whereKey($organization->id))->first(),
        ]);
    }

    public function update(Request $request, Organization $organization): RedirectResponse
    {
        $before = "{$organization->code} · {$organization->name}";
        $organization->update($this->validated($request, $organization));
        $after = "{$organization->code} · {$organization->name}";
        if ($after !== $before) {
            ActivityLog::record($request->user(), 'organizations', 'Renamed organization', "{$before} → {$after}");
        }

        return redirect('/admin/organizations')->with('status', "Saved {$organization->code}.");
    }

    public function destroy(Request $request, Organization $organization): RedirectResponse
    {
        $organization = $this->withNumbers(Organization::whereKey($organization->id))->first();

        $blockers = collect([
            $organization->officers_count ? "{$organization->officers_count} ".str('officer')->plural($organization->officers_count) : null,
            $organization->tickets_count ? "{$organization->tickets_count} ".str('ticket')->plural($organization->tickets_count) : null,
            $organization->signatories_count ? "{$organization->signatories_count} ".str('signatory')->plural($organization->signatories_count).' on its route' : null,
        ])->filter();

        if ($blockers->isNotEmpty()) {
            return back()->withErrors(['organization' => "{$organization->code} can't be deleted: it has "
                .$blockers->implode(', ').'. Only an empty organization (e.g. one added by mistake) can be deleted.']);
        }

        $organization->delete();
        ActivityLog::record($request->user(), 'organizations', 'Deleted organization', "{$organization->code} · {$organization->name}");

        return redirect('/admin/organizations')->with('status', "Deleted {$organization->code}.");
    }

    /** The numbers on each card, counted by the database in one query. */
    private function withNumbers(Builder $query): Builder
    {
        return $query->withCount([
            'officers',
            'signatories',
            'tickets',
            // Tickets with at least one paper still moving (waiting, or back with the officer)
            'tickets as active_tickets_count' => fn (Builder $q) => $q->whereHas('documents',
                fn (Builder $d) => $d->whereIn('status', [Document::PENDING, Document::RETURNED])),
        ]);
    }

    private function validated(Request $request, ?Organization $organization = null): array
    {
        // Codes are shown in capitals everywhere (DIGITS, SSC). Capitalize BEFORE checking,
        // so "digits" is caught as a duplicate of "DIGITS" instead of crashing on save.
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);

        return $request->validate([
            'code' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9-]+$/',
                Rule::unique('organizations')->ignore($organization?->id)],
            'name' => ['required', 'string', 'max:255'],
        ], [
            'code.regex' => 'Use letters, numbers and dashes only (e.g. DIGITS, SSC).',
            'code.unique' => 'Another organization already uses this code.',
        ]);
    }
}
