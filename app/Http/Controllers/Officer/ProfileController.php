<?php

namespace App\Http\Controllers\Officer;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The officer's own profile. The password form is Account\PasswordController.
 * Name and Student ID are NOT editable here: the admin verified them against
 * the uploaded ID, so changing them would make that verification meaningless.
 */
class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        $user = $request->user();

        return view('student-officer.profile', [
            'account' => $user,
            'positions' => User::OFFICER_POSITIONS,
            'ticketCount' => $user->tickets()->count(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        // Only these three fields are read; anything else in the request
        // (first_name, login_id, role, status...) is ignored
        $data = $request->validate([
            'position' => ['required', Rule::in(User::OFFICER_POSITIONS)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'phone' => ['nullable', 'regex:/^09\d{9}$/'],
        ], [
            'email.unique' => 'Another account already uses this email.',
            'phone.regex' => 'Enter an 11-digit number starting with 09.',
        ]);

        $user->update($data);

        return back()->with('status', 'Profile saved.');
    }
}
