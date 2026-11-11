<?php

namespace App\Http\Controllers\Signatory;

use App\Http\Controllers\Controller;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * For Review: the signatory's queue, one card per ticket (most urgent first, AUD-077), and a
 * panel for the selected ticket where each waiting document is approved,
 * returned, or rejected on its own (Batch rule), or all approved at once.
 */
class ReviewController extends Controller
{
    private const TABS = ['All', 'Single', 'Batch'];

    public function index(Request $request): View
    {
        $signatory = $request->user();
        $search = trim((string) $request->query('q', ''));

        // Only documents at this signatory's step; grouped into tickets afterwards.
        // The search picks whole tickets, so a Batch never shows only some of its papers
        $waiting = Document::atStepOf($signatory)
            ->where('status', Document::PENDING)
            ->when($search !== '', fn ($q) => $q->whereHas('ticket', fn ($t) => $t->search($search)))
            // documents: every paper of the ticket, for its status and so its deadline badge (AUD-077)
            ->with(['ticket' => fn ($q) => $q->withCount('documents')->with('documents', 'officer', 'organization.signatories')])
            ->oldest('updated_at')
            ->get();

        // Most urgent first (AUD-077): soonest Date Needed, then no date. The sort is
        // stable, so tickets with the same date keep the oldest-first order from above
        $queue = $waiting->groupBy('ticket_id')->map(fn ($documents) => (object) [
            'ticket' => $documents->first()->ticket,
            'documents' => $documents,
            'since' => $documents->min('updated_at'),
        ])->sortBy(fn ($item) => $item->ticket->urgencyKey())->values();

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
            'search' => $search,
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
