<?php

namespace App\Http\Controllers\Signatory;

use App\Http\Controllers\Controller;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * For Review: the signatory's queue, one card per ticket (oldest first), and a
 * panel for the selected ticket where each waiting document is approved,
 * returned, or rejected on its own (Batch rule), or all approved at once.
 */
class ReviewController extends Controller
{
    private const TABS = ['All', 'Single', 'Batch'];

    public function index(Request $request): View
    {
        $signatory = $request->user();

        // Only documents at this signatory's step; grouped into tickets afterwards
        $waiting = Document::atStepOf($signatory)
            ->where('status', Document::PENDING)
            ->with(['ticket' => fn ($q) => $q->withCount('documents')->with('officer', 'organization.signatories')])
            ->oldest('updated_at')
            ->get();

        // Already oldest first, so the queue keeps that order
        $queue = $waiting->groupBy('ticket_id')->map(fn ($documents) => (object) [
            'ticket' => $documents->first()->ticket,
            'documents' => $documents,
            'since' => $documents->min('updated_at'),
        ])->values();

        $isBatch = fn ($item) => $item->ticket->documents_count > 1;
        $counts = [
            'All' => $queue->count(),
            'Single' => $queue->reject($isBatch)->count(),
            'Batch' => $queue->filter($isBatch)->count(),
        ];

        $tab = in_array($request->query('type'), self::TABS, true) ? $request->query('type') : 'All';
        $shown = match ($tab) {
            'Single' => $queue->reject($isBatch)->values(),
            'Batch' => $queue->filter($isBatch)->values(),
            default => $queue,
        };

        $selected = $shown->first(fn ($item) => (int) $item->ticket->id === (int) $request->query('ticket')) ?? $shown->first();

        return view('signatory.review', [
            'queue' => $shown,
            'counts' => $counts,
            'tab' => $tab,
            'selected' => $selected,
            'route' => $selected ? $this->routeInfo($selected, $signatory->id) : null,
        ]);
    }

    /** "Step 1 of 4 · Adviser" and who gets the papers next, for the selected ticket. */
    private function routeInfo(object $item, int $signatoryId): object
    {
        $route = $item->ticket->organization->signatories;
        $step = $item->documents->first()->current_step;

        return (object) [
            'step' => $step,
            'total' => $route->count(),
            'title' => $route->firstWhere('id', $signatoryId)?->pivot->title,
            'next' => $route->first(fn ($s) => $s->pivot->step > $step),
        ];
    }
}
