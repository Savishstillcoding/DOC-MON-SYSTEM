<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * The admin checks a pending account's uploaded ID, then verifies or rejects it.
 * After the decision the ID file is deleted (decided 2026-10-31, data minimization);
 * reviewed_at and reviewed_by remain as proof that it was checked.
 */
class VerificationController extends Controller
{
    /** Opens the uploaded ID in the browser. Admin-only (route middleware). */
    public function showId(User $user): Response
    {
        $document = $user->idDocument;
        abort_if($document === null, 404);

        $extension = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf'][$document->mime] ?? 'bin';

        return response($document->data, 200, [
            'Content-Type' => $document->mime,
            'Content-Disposition' => "inline; filename=\"id-{$user->id}.{$extension}\"",
            // Personal data: never cached by the browser or a proxy, never guessed as another type
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function verify(Request $request, User $user): RedirectResponse
    {
        return $this->decide($request, $user, 'verified');
    }

    public function reject(Request $request, User $user): RedirectResponse
    {
        return $this->decide($request, $user, 'rejected');
    }

    private function decide(Request $request, User $user, string $status): RedirectResponse
    {
        $decided = DB::transaction(function () use ($request, $user, $status) {
            // Locked: two admins clicking at once can't both decide
            $user = User::lockForUpdate()->findOrFail($user->id);

            if ($user->status !== 'pending' || ! in_array($user->role, ['officer', 'signatory'], true)) {
                return null;
            }

            $user->forceFill([
                'status' => $status,
                'reviewed_at' => now(),
                'reviewed_by' => $request->user()->id,
            ])->save();
            $user->idDocument()->delete();

            return $user;
        });

        if ($decided === null) {
            return back()->withErrors(['verify' => 'This account is not waiting for verification anymore.']);
        }

        return back()->with('status', $status === 'verified'
            ? "Verified {$decided->displayName()}. They can log in now."
            : "Rejected {$decided->displayName()}. Their ID was deleted; they can sign up again with a clearer ID.");
    }
}
