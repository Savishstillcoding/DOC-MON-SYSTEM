<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;
use Throwable;

/**
 * "Forgot password?" (AUD-008), using Laravel's password broker:
 *   1. create/store: the user types their ID and email; if they match a verified
 *      account, a one-time link is emailed (through Brevo). The page answers the
 *      same either way, so it can't be used to find out which IDs or emails exist.
 *   2. edit/update: the link opens a form for the new password (same rules as Profile).
 *      The token is stored hashed in password_reset_tokens and expires in 60 minutes.
 */
class PasswordResetController extends Controller
{
    private const SENT = 'If the ID and email match a verified account, we emailed a reset link. Check your inbox and spam folder; the link works for 60 minutes.';

    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'login_id' => ['required', 'string', 'max:30'],
            'email' => ['required', 'email', 'max:255'],
        ]);

        try {
            // Both must belong to the same verified account (pending, rejected
            // or deactivated accounts can't log in, so they get no link either)
            Password::sendResetLink(
                ['login_id' => $data['login_id'], 'email' => $data['email'], 'status' => 'verified'],
                function (User $user, string $token) {
                    $user->sendPasswordResetNotification($token);
                    ActivityLog::record($user, 'accounts', 'Requested password reset', 'Reset link emailed');
                },
            );
        } catch (Throwable $e) {
            // Brevo down or the key expired: log it for the team, but answer the same
            Log::error('Password reset email failed: '.$e->getMessage());
        }

        return back()->with('status', self::SENT);
    }

    public function edit(Request $request, string $token): View
    {
        return view('auth.reset-password', ['token' => $token, 'email' => (string) $request->query('email', '')]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $role = null;

        $result = Password::reset(
            ['email' => $data['email'], 'status' => 'verified'] + $request->only('password', 'password_confirmation', 'token'),
            function (User $user, string $password) use (&$role) {
                // The 'hashed' cast hashes it; a new remember token ends "remember me" logins
                $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();

                // Sign the account out everywhere (sessions are stored in the database)
                DB::table('sessions')->where('user_id', $user->id)->delete();

                ActivityLog::record($user, 'accounts', 'Reset password', 'With the emailed link');
                $role = $user->role;
            },
        );

        if ($result !== Password::PASSWORD_RESET) {
            return back()->withInput($request->only('email'))
                ->withErrors(['email' => 'This reset link is invalid or has expired. Ask for a new one.']);
        }

        return redirect('/login/'.$role)->with('status', 'Password changed. Log in with your new password.');
    }
}
