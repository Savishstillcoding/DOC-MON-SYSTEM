<?php

namespace App\Http\Controllers\Auth;

/*
| READING GUIDE 9/15: LoginController
|
| WHAT: a controller: the log-in forms and the log-out button.
| USED FOR: letting users in and out.
| KEY IDEA: right ID + password + role AND a verified account → let in.
|           Any mistake → one message ("Wrong ID or password"), so it reveals nothing.
*/

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * LoginController = logging in and out.
 * Each role has its own log-in page, but all of them check the same login_id column.
 */
class LoginController extends Controller
{
    /** ID_FIELDS = the name of the ID box on each log-in page: student ID, employee ID, or username. */
    private const ID_FIELDS = [
        'officer' => 'student_id',
        'signatory' => 'employee_id',
        'admin' => 'username',
    ];

    /**
     * store() = what happens when someone presses Log In.
     * How it works:
     *   1. Check the ID + password + role. Wrong → "Wrong ID or password." (logged)
     *   2. Right, but the account isn't verified → log them out again and say why
     *      (waiting for the admin / rejected with the reason / deactivated).
     *   3. Verified → give a new session ID, log it, and open their dashboard.
     */
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

        // A session ID is the browser's "you're logged in" ticket. Give a brand-new one now,
        // so an old (possibly stolen) session ID is useless
        $request->session()->regenerate();
        ActivityLog::record($account, 'logins', 'Logged in', $this->where($request, $role));

        // Back to the page they originally wanted, otherwise their dashboard
        return redirect()->intended("/{$role}/dashboard");
    }

    /**
     * recordFailure() = writes a failed log-in into the Activity Log, so the admin can
     * spot password guessing. The typed password is never saved.
     */
    private function recordFailure(Request $request, string $role, string $loginId): void
    {
        $account = User::where('login_id', $loginId)->where('role', $role)->first();

        ActivityLog::record(null, 'logins', 'Failed log-in', ($account
            ? "Wrong password for {$account->displayName()} ({$account->login_id})"
            : 'Unknown ID').' · '.$this->where($request, $role));
    }

    /** where() = which page and internet address the log-in came from: "Officer log-in page · IP 203.0.113.5". */
    private function where(Request $request, string $role): string
    {
        return (User::ROLE_LABELS[$role] ?? ucfirst($role))." log-in page · IP {$request->ip()}";
    }

    /** destroy() = Log Out: ends the session completely and goes back to /login. */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        // Throw away the session completely, and make a new CSRF token (the secret code
        // every form carries, so other websites can't send forms in your name)
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
