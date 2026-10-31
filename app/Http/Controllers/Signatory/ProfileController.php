<?php

namespace App\Http\Controllers\Signatory;

use App\Http\Controllers\Controller;
use App\Models\DocumentAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The signatory's own profile. Name and Employee ID were verified by the admin,
 * and the title (Adviser, VPSD...) comes from the admin's route setup, so only
 * email and phone are editable here. The password form is Account\PasswordController.
 */
class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        $user = $request->user();

        return view('signatory.profile', [
            'account' => $user,
            // Each route this signatory is on, with its length for "Step 1 of 4"
            'routes' => $user->signatoryOrganizations()->withCount('signatories')->get(),
            'signedCount' => DocumentAction::where('user_id', $user->id)->where('action', DocumentAction::APPROVED)->count(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        // Only these two fields are read; anything else in the request is ignored
        $data = $request->validate([
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
