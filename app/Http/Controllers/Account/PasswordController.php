<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

/**
 * "Update Password" on the Profile page, shared by officers and signatories,
 * so both roles follow exactly the same password rules (SEC-04).
 */
class PasswordController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        // Own error bag ("password"), so these errors show under the Security form only
        $data = $request->validateWithBag('password', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::defaults()],
        ], [
            'current_password.current_password' => 'Your current password is wrong.',
            'password.different' => 'The new password must be different from the current one.',
        ]);

        // The 'hashed' cast on User hashes it before saving; a new remember token ends "remember me" logins
        $user = $request->user();
        $user->forceFill(['password' => $data['password'], 'remember_token' => Str::random(60)])->save();

        // Sign out every OTHER device (AUD-068): if someone else knew the old password,
        // their session ends now. This browser gets a fresh session ID and stays logged in
        $request->session()->regenerate();
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))
                ->where('user_id', $user->id)
                ->where('id', '!=', $request->session()->getId())
                ->delete();
        }

        ActivityLog::record($user, 'accounts', 'Changed password', 'From the Profile page · other devices signed out');

        return back()->with('status', 'Password updated. Any other device using your account was signed out.');
    }
}
