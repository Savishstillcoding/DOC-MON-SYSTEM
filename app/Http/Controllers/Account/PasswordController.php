<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        // The 'hashed' cast on User hashes it before saving
        $request->user()->update(['password' => $data['password']]);

        return back()->with('status', 'Password updated.');
    }
}
