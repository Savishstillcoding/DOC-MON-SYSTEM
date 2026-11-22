<?php

namespace App\Http\Controllers\Admin;

/*
| READING GUIDE 10/15: VerificationController
|
| WHAT: a controller: the admin's User Verification page.
| USED FOR: checking new accounts against their ID photo, then verifying or
|           rejecting them (with a reason).
| KEY IDEA: the ID photo is deleted after the decision (keep only what we need).
*/

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
 * VerificationController = the admin's User Verification page.
 * The admin opens a pending account's ID photo, ticks the checklist, then verifies it
 * or rejects it with a reason. After the decision the ID photo is deleted (we keep
 * only what we need); the record of who decided, when, and why stays.
 */
class VerificationController extends Controller
{
    /** TABS = the tabs on the page and which accounts each shows (All, Student Officers, Signatories). */
    private const TABS = ['All' => null, 'Student Officers' => 'officer', 'Signatories' => 'signatory'];

    /**
     * index() = the list of accounts waiting for verification.
     * Oldest first (whoever waited longest), 5 per page, with tabs and a search box.
     */
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

    /**
     * showId() = shows one ID photo to the admin.
     * Every view is written in the Activity Log, and the browser is told not to keep a copy.
     */
    public function showId(User $user): Response
    {
        $document = $user->idDocument;
        abort_if($document === null, 404);

        // An ID photo is personal data, so every time the admin opens one it is written in
        // the Activity Log: who looked, and at whose ID.
        ActivityLog::record(request()->user(), 'verification', 'Viewed ID', "{$user->displayName()} ({$user->login_id})");

        $extension = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf'][$document->mime] ?? 'bin';

        return response($document->data, 200, [
            'Content-Type' => $document->mime,
            'Content-Disposition' => "inline; filename=\"id-{$user->id}.{$extension}\"",
            // Tell the browser: don't keep a saved copy of this photo (no-store), and
            // don't try to guess the file type (nosniff)
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * review() = what happens when the admin presses Verify or Reject.
     * How it works:
     *   1. Verify needs all 3 checklist boxes ticked; Reject needs a reason.
     *   2. Lock the account (so two admins can't decide at the same time).
     *   3. Save the decision (who, when, reason) and DELETE the ID photo.
     *   4. Log it, then go back to the list.
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

    /** findPending() = finds one pending account by its id (for a link to a specific person). */
    private function findPending(int $id): ?User
    {
        return User::where('status', 'pending')->whereIn('role', ['officer', 'signatory'])
            ->with(['organization', 'idDocument:id,user_id,mime,size'])->find($id);
    }
}
