<?php

namespace App\Http\Controllers\Signatory;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\DocumentAction;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The signatory's dashboard: the papers waiting for their signature, their
 * numbers, and what they signed recently. Everything is for the logged-in
 * signatory only; nobody sees another signatory's queue.
 */
class DashboardController extends Controller
{
    /** The tabs above the review list. */
    private const TABS = ['All', 'Single', 'Batch'];

    public function index(Request $request): View
    {
        $signatory = $request->user();

        // Oldest first: updated_at is when the paper reached this step
        // (moved here by an approval, or resubmitted after a revision)
        $waiting = Document::atStepOf($signatory)
            ->where('status', Document::PENDING)
            ->with(['ticket' => fn ($q) => $q->withCount('documents')->with('officer')])
            ->oldest('updated_at')
            ->get();

        $isBatch = fn (Document $d) => $d->ticket->documents_count > 1;
        $counts = [
            'All' => $waiting->count(),
            'Single' => $waiting->reject($isBatch)->count(),
            'Batch' => $waiting->filter($isBatch)->count(),
        ];

        $tab = in_array($request->query('type'), self::TABS, true) ? $request->query('type') : 'All';
        $documents = match ($tab) {
            'Single' => $waiting->reject($isBatch),
            'Batch' => $waiting->filter($isBatch),
            default => $waiting,
        };

        $myActions = DocumentAction::where('user_id', $signatory->id);

        return view('signatory.dashboard', [
            'documents' => $documents,
            'counts' => $counts,
            'tab' => $tab,
            'stats' => [
                'waiting' => $waiting->count(),
                'signedThisWeek' => (clone $myActions)->where('action', DocumentAction::APPROVED)
                    ->where('created_at', '>=', now()->startOfWeek())->count(),
                // Returned by me and still with the officer for revision
                'withOfficer' => Document::atStepOf($signatory)->where('status', Document::RETURNED)->count(),
                'rejected' => (clone $myActions)->where('action', DocumentAction::REJECTED)->count(),
            ],
            'recent' => (clone $myActions)->with('document.ticket')->latest()->latest('id')->limit(5)->get(),
        ]);
    }
}
