<?php

namespace App\Http\Controllers\Signatory;

use App\Http\Controllers\Controller;
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
        // Not in $fillable, so set directly; "Available" needs no back-at time
        $user->availability = $data['availability'];
        $user->back_at = $data['availability'] === 'Available' ? null : $data['back_at'];
        $user->save();

        return back()->with('status', 'Status updated.');
    }
}
