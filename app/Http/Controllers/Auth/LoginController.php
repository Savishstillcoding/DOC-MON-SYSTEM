<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
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
            // Same message for "no such account" and "wrong password", so the
            // form doesn't reveal which IDs exist.
            return back()->withErrors([$idField => 'Wrong ID or password.'])->onlyInput($idField);
        }

        // Correct password, but the admin hasn't verified the account yet
        // (or rejected it): sign them straight back out.
        $status = $request->user()->status;

        if ($status !== 'verified') {
            Auth::logout();

            $message = $status === 'pending'
                ? 'Your account is waiting for admin verification.'
                : 'Your account was not approved. Please contact the admin.';

            return back()->withErrors([$idField => $message])->onlyInput($idField);
        }

        // New session ID after logging in, so an old (possibly stolen) session ID is useless
        $request->session()->regenerate();

        // Back to the page they originally wanted, otherwise their dashboard
        return redirect()->intended("/{$role}/dashboard");
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
