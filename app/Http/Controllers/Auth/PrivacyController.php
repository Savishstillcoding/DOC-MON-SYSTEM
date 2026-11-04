<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The privacy notice (AUD-078, Data Privacy Act of 2012). Public, so anyone can read it
 * before signing up. Someone logged in who hasn't accepted also gets the Accept button.
 */
class PrivacyController extends Controller
{
    public function show(Request $request): View
    {
        return view('privacy', [
            'account' => $request->user(),
            'contactEmail' => config('services.privacy.email'),
            'years' => User::RETENTION_YEARS,
        ]);
    }

    public function accept(Request $request): RedirectResponse
    {
        $user = $request->user();

        // Keeps the first time, if someone accepts twice (e.g. in two tabs)
        if ($user->privacy_accepted_at === null) {
            $user->forceFill(['privacy_accepted_at' => now()])->save();
            ActivityLog::record($user, 'accounts', 'Accepted privacy notice', 'At log-in');
        }

        return redirect("/{$user->role}/dashboard");
    }
}
