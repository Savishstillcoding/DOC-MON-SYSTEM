<?php

namespace App\Http\Controllers\Officer;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\DocumentAction;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The officer's Signatories page (AUD-038): their organization's approval route,
 * each signatory's live availability, and where THIS officer's papers are.
 */
class SignatoryController extends Controller
{
    public function index(Request $request): View
    {
        $officer = $request->user();
        $route = $officer->organization?->signatories ?? collect();
        $mine = fn ($q) => $q->where('officer_id', $officer->id);

        // This officer's papers waiting at each step: [step => count]
        $waiting = Document::whereHas('ticket', $mine)
            ->where('status', Document::PENDING)
            ->selectRaw('current_step, count(*) as total')
            ->groupBy('current_step')
            ->pluck('total', 'current_step');

        // What each signatory did with this officer's papers: [user_id][action] => count
        $done = DocumentAction::whereHas('document.ticket', $mine)
            ->whereIn('action', [DocumentAction::APPROVED, DocumentAction::RETURNED])
            ->selectRaw('user_id, action, count(*) as total')
            ->groupBy('user_id', 'action')
            ->get()
            ->groupBy('user_id');

        $signatories = $route->map(fn ($s) => (object) [
            'step' => $s->pivot->step,
            'title' => $s->pivot->title,
            'name' => $s->displayName(),
            'initials' => $s->initials(),
            'availability' => $s->availability ?? 'Available',
            'back_at' => $s->back_at,
            'pending' => (int) ($waiting[$s->pivot->step] ?? 0),
            'approved' => (int) ($done->get($s->id)?->firstWhere('action', DocumentAction::APPROVED)?->total ?? 0),
            'returned' => (int) ($done->get($s->id)?->firstWhere('action', DocumentAction::RETURNED)?->total ?? 0),
        ]);

        return view('student-officer.signatories', [
            'organization' => $officer->organization,
            'signatories' => $signatories,
        ]);
    }
}
