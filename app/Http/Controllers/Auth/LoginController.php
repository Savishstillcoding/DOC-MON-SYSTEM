<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Logs users in and out. Each role has its own log-in page, and each page
 * names its ID field differently, but they all match users.login_id.
 */
class LoginController extends Controller
{
    /**
     * The ID field on each role's log-in form (resources/views/auth/*-login.blade.php).
     */
    private const ID_FIELDS = [
        'officer' => 'student_id',
        'signatory' => 'employee_id',
        'admin' => 'username',
    ];

    public function store(Request $request, string $role): RedirectResponse
    {
        $idField = self::ID_FIELDS[$role];

        $credentials = $request->validate([
            $idField => ['required', 'string', 'max:30'],
            'password' => ['required', 'string'],
        ]);

        // Only matches an account with this login ID, this password AND this
        // role, so an officer can't sign in through the admin form.
        $passwordIsCorrect = Auth::attempt([
            'login_id' => $credentials[$idField],
            'password' => $credentials['password'],
            'role' => $role,
        ]);

        if (! $passwordIsCorrect) {
            $this->recordFailure($request, $role, $credentials[$idField]);

            // Same message for "no such account" and "wrong password", so the
            // form doesn't reveal which IDs exist.
            return back()->withErrors([$idField => 'Wrong ID or password.'])->onlyInput($idField);
        }

        // Correct password, but the admin hasn't verified the account yet
        // (or rejected it): sign them straight back out.
        $account = $request->user();   // read before logout(), which forgets the user
        $status = $account->status;

        if ($status !== 'verified') {
            Auth::logout();
            ActivityLog::record($account, 'logins', 'Blocked log-in', "Account is {$account->statusLabel()} · ".$this->where($request, $role));

            $message = match ($status) {
                'pending' => 'Your account is waiting for admin verification.',
                // The admin's reason, so the person knows what to fix before signing up again
                'rejected' => 'Your account was not approved'
                    .($account->review_note ? ': '.rtrim($account->review_note, '.!? ').'.' : '.')
                    .' You can sign up again.',
                'deactivated' => 'Your account was deactivated. Please contact the admin.',
                default => 'Your account cannot log in. Please contact the admin.',
            };

            return back()->withErrors([$idField => $message])->onlyInput($idField);
        }

        // New session ID after logging in, so an old (possibly stolen) session ID is useless
        $request->session()->regenerate();
        ActivityLog::record($account, 'logins', 'Logged in', $this->where($request, $role));

        // Back to the page they originally wanted, otherwise their dashboard
        return redirect()->intended("/{$role}/dashboard");
    }

    /**
     * A failed log-in, so password guessing shows up in the Activity Log (AUD-071).
     * The typed password is never stored, and neither is an unknown ID: people
     * sometimes type their password into the ID box by mistake.
     */
    private function recordFailure(Request $request, string $role, string $loginId): void
    {
        $account = User::where('login_id', $loginId)->where('role', $role)->first();

        ActivityLog::record(null, 'logins', 'Failed log-in', ($account
            ? "Wrong password for {$account->displayName()} ({$account->login_id})"
            : 'Unknown ID').' · '.$this->where($request, $role));
    }

    /** "Officer log-in page · IP 203.0.113.5" */
    private function where(Request $request, string $role): string
    {
        return (User::ROLE_LABELS[$role] ?? ucfirst($role))." log-in page · IP {$request->ip()}";
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        // Throw away the session and its CSRF token completely
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
