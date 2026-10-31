<?php

namespace App\Http\Controllers\Signatory;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Ticket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * A signatory approves, returns, or rejects documents (DFD process 4.0).
 * The rules themselves live in Document::applyAction():
 * - only the signatory at the document's current step may act, in route order;
 * - approve moves it to the next step, or makes it Approved after the last step;
 * - return sends it to the officer; after resubmitting it comes back to the SAME step;
 * - reject is final; the officer files a new ticket.
 * Return and reject need remarks, so the officer knows why.
 */
class DocumentActionController extends Controller
{
    /** One document: the buttons on the dashboard and the For Review page. */
    public function store(Request $request, Document $document): RedirectResponse
    {
        $signatory = $request->user();

        $data = $request->validate([
            'action' => ['required', Rule::in(['approve', 'return', 'reject'])],
            'remarks' => ['nullable', 'string', 'max:1000', 'required_if:action,return,reject'],
        ], [
            'remarks.required_if' => 'Write a short reason so the officer knows what to fix.',
        ]);

        // The transaction + lockForUpdate makes two clicks (or two tabs) wait for
        // each other: the second one sees the document already moved and stops.
        $document = DB::transaction(function () use ($document, $signatory, $data) {
            $document = Document::with('ticket.organization.signatories')->lockForUpdate()->findOrFail($document->id);

            // Same answer for "not your step", "already handled" and "another
            // organization's document", so nothing leaks about other tickets
            return $document->applyAction($signatory, $data['action'], $data['remarks'] ?? null) ? $document : null;
        });

        if ($document === null) {
            return back()->withErrors(['action' => 'This document is not waiting for your signature anymore.']);
        }

        $label = "{$document->title} ({$document->ticket->code})";

        return back()->with('status', match ($data['action']) {
            'approve' => "Approved {$label}. ".$this->nextHolder($document),
            'return' => "Returned {$label} to the officer for revision.",
            'reject' => "Rejected {$label}. The officer will see it on their tickets.",
        });
    }

    /**
     * "Approve all": every document of this ticket that is waiting for this
     * signatory, approved together. Each one is still checked and recorded
     * separately, exactly as if its own Approve button was clicked.
     */
    public function approveAll(Request $request, Ticket $ticket): RedirectResponse
    {
        $signatory = $request->user();

        $approved = DB::transaction(fn () => Document::with('ticket.organization.signatories')
            ->where('ticket_id', $ticket->id)
            ->lockForUpdate()
            ->get()
            ->filter(fn (Document $document) => $document->applyAction($signatory, 'approve')));

        if ($approved->isEmpty()) {
            return back()->withErrors(['action' => 'No documents of this ticket are waiting for your signature.']);
        }

        $count = $approved->count();

        return back()->with('status',
            "Approved {$count} ".str('document')->plural($count)." of {$ticket->code}. ".$this->nextHolder($approved->first()));
    }

    /** Where the paper goes after an approval, for the green message. */
    private function nextHolder(Document $document): string
    {
        if ($document->status === Document::APPROVED) {
            return 'This was the final signature; the officer can collect the paper.';
        }

        $next = $document->ticket->organization->signatories->firstWhere('pivot.step', $document->current_step);

        return "Pass the paper to {$next->displayName()} ({$next->pivot->title}).";
    }
}
