<?php

namespace App\Http\Controllers\Signatory;

/*
| READING GUIDE 12/15: DocumentActionController
|
| WHAT: a controller: the signatory's Approve / Return / Reject / Approve all
|       buttons.
| USED FOR: handling a signatory's decision on a paper.
| KEY IDEA: it locks the paper, then calls Document::applyAction(), so a double click
|           can't approve twice.
*/

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Ticket;
use App\Services\TicketAlerts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * DocumentActionController = the signatory's Approve / Return / Reject / Approve all buttons.
 * The actual rules are in Document::applyAction(); this file only handles the click.
 * Return and Reject need remarks, so the officer knows why.
 */
class DocumentActionController extends Controller
{
    /**
     * store() = a click on Approve, Return or Reject for ONE paper.
     * How it works:
     *   1. Check the action, and that Return/Reject have remarks.
     *   2. Lock the paper, then run Document::applyAction().
     *   3. Not this signatory's turn (or already done)? Show an error.
     *   4. Done → send Telegram and show a green message.
     */
    public function store(Request $request, Document $document, TicketAlerts $alerts): RedirectResponse
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

        // Telegram, after the transaction: the action is saved even if this fails
        $alerts->acted(collect([$document]), $signatory, $data['action'], $data['remarks'] ?? null);

        $label = "{$document->title} ({$document->ticket->code})";

        return back()->with('status', match ($data['action']) {
            'approve' => "Approved {$label}.".$this->finalNote($document),
            'return' => "Returned {$label} to the officer for revision.",
            'reject' => "Rejected {$label}. The officer will see it on their tickets.",
        });
    }

    /**
     * approveAll() = approves every paper of one ticket that is waiting for this signatory.
     * Each paper is still checked and recorded on its own, as if each was clicked.
     */
    public function approveAll(Request $request, Ticket $ticket, TicketAlerts $alerts): RedirectResponse
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

        $alerts->acted($approved->values(), $signatory, 'approve');   // one message for all of them

        $count = $approved->count();

        return back()->with('status',
            "Approved {$count} ".str('document')->plural($count)." of {$ticket->code}.".$this->finalNote($approved->first()));
    }

    /**
     * finalNote() = adds " This was the final signature." to the green message when
     * the last signatory approved.
     */
    private function finalNote(Document $document): string
    {
        return $document->status === Document::APPROVED ? ' This was the final signature.' : '';
    }
}
