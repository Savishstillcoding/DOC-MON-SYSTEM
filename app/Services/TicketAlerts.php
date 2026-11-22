<?php

namespace App\Services;

/*
| READING GUIDE 13/15: TicketAlerts
|
| WHAT: a service (a helper class for one job): the Telegram message planner.
| USED FOR: deciding who gets which Telegram message when a paper moves.
| KEY IDEA: sent only after saving, so a slow Telegram never blocks anything.
*/

use App\Models\Document;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * TicketAlerts = decides who gets which Telegram message when a paper moves.
 *   officer submits     → first signatory: "a paper is waiting for your signature"
 *   signatory approves  → officers: approved (or fully approved)
 *                         next signatory: "a paper is waiting for your signature"
 *   signatory returns   → officers: returned, with the remarks
 *   signatory rejects   → officers: rejected, with the remarks
 *   officer resubmits   → the same signatory: "the revised paper is back"
 * "officers" = every verified officer of the org (not only the one who filed it).
 * Users who haven't linked Telegram are skipped.
 */
class TicketAlerts
{
    public function __construct(private Telegram $telegram) {}

    /** submitted() = a new ticket was filed: tell the first signatory. */
    public function submitted(Ticket $ticket): void
    {
        $ticket->loadMissing('documents', 'officer', 'organization.signatories');
        $first = $ticket->organization->signatories->first();

        $this->toSignatory($first, $ticket, $ticket->documents, $this->waiting($ticket->documents));
    }

    /**
     * acted() = a signatory approved, returned or rejected one or more papers of a ticket.
     * Each officer gets ONE message, even for "Approve all".
     *
     * @param  Collection<int, Document>  $documents
     */
    public function acted(Collection $documents, User $signatory, string $action, ?string $remarks = null): void
    {
        $first = $documents->first();
        $ticket = $first->ticket->loadMissing('officer', 'organization.signatories');
        $route = $ticket->organization->signatories;
        $by = $route->firstWhere('id', $signatory->id)?->pivot->title ?? $signatory->displayName();
        $what = $this->papers($documents);

        if ($action === 'approve' && $first->status === Document::APPROVED) {
            $this->toOfficer($ticket, "🎉 {$what} fully approved (final signature by {$by}). You can collect the paper.");

            return;
        }

        if ($action === 'approve') {
            $next = $route->firstWhere('pivot.step', $first->current_step);
            $this->toOfficer($ticket, "✅ {$what} approved by {$by}. Next: bring the paper to {$next->pivot->title} ({$next->displayName()}) to sign.");
            $this->toSignatory($next, $ticket, $documents, $this->waiting($documents));

            return;
        }

        $this->toOfficer($ticket, ($action === 'return' ? "↩️ {$what} returned for revision" : "❌ {$what} rejected")
            ." by {$by}.\nRemarks: {$remarks}"
            .($action === 'return' ? "\nRevise it, then resubmit it on My Tickets." : "\nFile a new ticket if you still need it.")
            // If the whole ticket is now Rejected, tell them its Ref No. can be used again
            .($action === 'reject' && $ticket->active_ref_no === null ? " You can use Ref No. {$ticket->ref_no} again." : ''));
    }

    /** resubmitted() = the officer fixed a returned paper: tell the same signatory it's back. */
    public function resubmitted(Document $document): void
    {
        $ticket = $document->ticket->loadMissing('officer', 'organization.signatories');
        $signatory = $ticket->organization->signatories->firstWhere('pivot.step', $document->current_step);

        $this->toSignatory($signatory, $ticket, collect([$document]), 'Revised paper is back for your signature');
    }

    /** toOfficer() = sends the message to every verified officer of the org who linked Telegram. */
    private function toOfficer(Ticket $ticket, string $line): void
    {
        // unique(): if one person linked two accounts to the same Telegram, they get the message once, not twice
        $chats = User::where('role', 'officer')->where('status', 'verified')
            ->where('organization_id', $ticket->organization_id)
            ->whereNotNull('telegram_chat_id')
            ->pluck('telegram_chat_id')->unique();

        foreach ($chats as $chat) {
            $this->telegram->send($chat, "DOC-MON · {$ticket->code} · {$ticket->title}\n{$line}\n\n".url('/officer/tickets'));
        }
    }

    /**
     * toSignatory() = sends one message to one signatory, listing the papers.
     *
     * @param  Collection<int, Document>  $documents
     */
    private function toSignatory(?User $signatory, Ticket $ticket, Collection $documents, string $line): void
    {
        $this->telegram->send($signatory?->telegram_chat_id,
            "📄 {$line}\n{$ticket->code} · {$ticket->title}\n"
            .$documents->map(fn (Document $d) => "• {$d->type}: {$d->title}")->implode("\n")
            ."\nFrom {$ticket->officer->displayName()} ({$ticket->organization->code})\n\n".url('/signatory/review'));
    }

    /**
     * papers() = how the papers are named in a message: 'Paper "Budget Plan"' or "3 papers".
     *
     * @param  Collection<int, Document>  $documents
     */
    private function papers(Collection $documents): string
    {
        $count = $documents->count();

        return $count === 1 ? "Paper “{$documents->first()->title}”" : "{$count} papers";
    }

    /**
     * waiting() = "A paper is waiting for your signature" or "3 papers are waiting ...".
     *
     * @param  Collection<int, Document>  $documents
     */
    private function waiting(Collection $documents): string
    {
        $count = $documents->count();

        return ($count === 1 ? 'A paper is' : "{$count} papers are").' waiting for your signature';
    }
}
