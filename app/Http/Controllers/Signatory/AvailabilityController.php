<?php

namespace App\Http\Controllers\Signatory;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * A signatory's availability ("In a Meeting · back at 2:00 PM"),
 * shown to officers on their ticket cards.
 */
class AvailabilityController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'availability' => ['required', Rule::in(User::AVAILABILITY)],
            'back_at' => ['nullable', 'string', 'max:30'],
        ], [
            'availability.required' => 'Choose a status.',
        ]);

        $user = $request->user();
        $before = trim(($user->availability ?? 'Available').($user->back_at ? " (back {$user->back_at})" : ''));

        // Not in $fillable, so set directly; "Available" needs no back-at time
        $user->availability = $data['availability'];
        $user->back_at = $data['availability'] === 'Available' ? null : ($data['back_at'] ?? null);
        $user->save();

        $after = $user->availability.($user->back_at ? " (back {$user->back_at})" : '');
        if ($after !== $before) {
            ActivityLog::record($user, 'accounts', 'Changed availability', "{$before} → {$after}");
        }

        return back()->with('status', 'Status updated.');
    }
}
