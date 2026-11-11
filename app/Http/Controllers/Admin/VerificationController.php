<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * User Verification: the admin opens a pending account's uploaded ID, ticks the
 * checklist, then approves it, or rejects it with a reason the person will see.
 * After the decision the ID file is deleted (decided 2026-10-31, data minimization);
 * reviewed_at, reviewed_by and review_note remain as the record.
 */
class VerificationController extends Controller
{
    /** Tab label => role shown (null = both). */
    private const TABS = ['All' => null, 'Student Officers' => 'officer', 'Signatories' => 'signatory'];

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));

        $pending = fn () => User::where('status', 'pending')
            ->whereIn('role', ['officer', 'signatory'])
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('login_id', 'like', "%{$search}%")));

        $counts = collect(self::TABS)->map(fn ($role) => $pending()->when($role, fn ($q) => $q->where('role', $role))->count());
        $tab = array_key_exists($request->query('tab'), self::TABS) ? $request->query('tab') : 'All';

        // Oldest first: whoever has waited longest is reviewed first.
        // Only the ID's type and size are loaded here, never the file itself.
        $requests = $pending()
            ->when(self::TABS[$tab], fn ($q, $role) => $q->where('role', $role))
            ->with(['organization', 'idDocument:id,user_id,mime,size'])
            ->oldest()->oldest('id')
            ->paginate(5)
            ->withQueryString();

        $selected = $requests->first(fn (User $u) => (int) $u->id === (int) $request->query('user'))
            ?? ($request->query('user') ? $this->findPending((int) $request->query('user')) : null)
            ?? $requests->first();

        return view('admin.verification', compact('requests', 'counts', 'tab', 'search', 'selected'));
    }

    /** Opens the uploaded ID in the browser (also used as the <img> in the panel). Admin-only. */
    public function showId(User $user): Response
    {
        $document = $user->idDocument;
        abort_if($document === null, 404);

        // Personal data: every view is on record, who and whose (AUD-072)
        ActivityLog::record(request()->user(), 'verification', 'Viewed ID', "{$user->displayName()} ({$user->login_id})");

        $extension = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf'][$document->mime] ?? 'bin';

        return response($document->data, 200, [
            'Content-Type' => $document->mime,
            'Content-Disposition' => "inline; filename=\"id-{$user->id}.{$extension}\"",
            // Personal data: never cached by the browser or a proxy, never guessed as another type
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Approve (all three checklist items ticked) or reject (a reason is required,
     * shown to the person at log-in).
     */
    public function review(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['verify', 'reject'])],
            'name_matches' => ['exclude_unless:decision,verify', 'accepted'],
            'id_matches' => ['exclude_unless:decision,verify', 'accepted'],
            'photo_clear' => ['exclude_unless:decision,verify', 'accepted'],
            'review_note' => ['nullable', 'string', 'max:500', 'required_if:decision,reject'],
        ], [
            'name_matches.accepted' => 'Tick every checklist item before approving.',
            'id_matches.accepted' => 'Tick every checklist item before approving.',
            'photo_clear.accepted' => 'Tick every checklist item before approving.',
            'review_note.required_if' => 'Write the reason, so they know what to fix.',
        ]);

        $status = $data['decision'] === 'verify' ? 'verified' : 'rejected';

        $decided = DB::transaction(function () use ($request, $user, $status, $data) {
            // Locked: two admins clicking at once can't both decide
            $user = User::lockForUpdate()->findOrFail($user->id);

            if ($user->status !== 'pending' || ! in_array($user->role, ['officer', 'signatory'], true)) {
                return null;
            }

            $user->forceFill([
                'status' => $status,
                'reviewed_at' => now(),
                'reviewed_by' => $request->user()->id,
                'review_note' => $status === 'rejected' ? $data['review_note'] : null,
            ])->save();
            $user->idDocument()->delete();

            ActivityLog::record($request->user(), 'verification',
                $status === 'verified' ? 'Approved account' : 'Rejected account',
                $user->displayName().' ('.collect([$user->roleLabel(), $user->organization?->code, $user->position])->filter()->implode(' · ').')'
                .($status === 'rejected' ? " — {$data['review_note']}" : ''));

            return $user;
        });

        if ($decided === null) {
            return back()->withErrors(['verify' => 'This account is not waiting for verification anymore.']);
        }

        // Back to the queue; the next request opens automatically
        return redirect('/admin/verification')->with('status', $status === 'verified'
            ? "Approved {$decided->displayName()}. They can log in now."
            : "Rejected {$decided->displayName()}. Their ID was deleted; they will see your reason when they try to log in.");
    }

    private function findPending(int $id): ?User
    {
        return User::where('status', 'pending')->whereIn('role', ['officer', 'signatory'])
            ->with(['organization', 'idDocument:id,user_id,mime,size'])->find($id);
    }
}
