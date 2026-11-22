<?php

namespace App\Http\Controllers\Signatory;

/*
| READING GUIDE 15/15: AvailabilityController
|
| WHAT: a controller: the signatory's status form.
| USED FOR: setting their status (Available, In a Meeting, ...) and "back at"
|           time, which officers see before walking to the office.
| KEY IDEA: it also saves WHEN it was set ("set 9:14 AM"), so old statuses don't fool anyone.
*/

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * AvailabilityController = the signatory's status form ("In a Meeting · back at 2:00 PM"),
 * which officers see on their ticket cards.
 */
class AvailabilityController extends Controller
{
    /**
     * update() = what happens when the signatory saves their status.
     * How it works:
     *   1. Check the status is one of User::AVAILABILITY.
     *   2. Save it with the "back at" time (cleared for Available) and the time it was set.
     *   3. If it changed, write "old → new" in the Activity Log.
     */
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'availability' => ['required', Rule::in(array_keys(User::AVAILABILITY))],
            'back_at' => ['nullable', 'string', 'max:30'],
        ], [
            'availability.required' => 'Choose a status.',
        ]);

        $user = $request->user();
        $before = trim(($user->availability ?? 'Available').($user->back_at ? " (back {$user->back_at})" : ''));

        // These columns aren't fillable, so they're set one by one here, on purpose.
        // "Available" needs no back-at time, so it's cleared.
        $user->availability = $data['availability'];
        $user->back_at = $data['availability'] === 'Available' ? null : ($data['back_at'] ?? null);
        // Saved every time, even when picking the same status again: "still in the meeting"
        // is news too, and the "set 9:14 AM" time on officers' pages updates
        $user->availability_updated_at = now();
        $user->save();

        $after = $user->availability.($user->back_at ? " (back {$user->back_at})" : '');
        if ($after !== $before) {
            ActivityLog::record($user, 'accounts', 'Changed availability', "{$before} → {$after}");
        }

        return back()->with('status', 'Status updated.');
    }
}
