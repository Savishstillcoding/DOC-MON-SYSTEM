<?php

namespace App\Services;

use App\Models\Document;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * The Telegram messages sent when papers move (Increment 7: notifications).
 *   officer submits     → step-1 signatory: a paper is waiting
 *   signatory approves  → officer: approved (or fully approved); next signatory: a paper is waiting
 *   signatory returns   → officer: returned, with the remarks
 *   signatory rejects   → officer: rejected, with the remarks
 *   officer resubmits   → the same signatory: the revised paper is back
 * Users who haven't connected Telegram (no telegram_chat_id) are skipped.
 * Called after the database work is saved, so a slow Telegram never holds a lock.
 */
class TicketAlerts
{
    public function __construct(private Telegram $telegram) {}

    public function submitted(Ticket $ticket): void
    {
        $ticket->loadMissing('documents', 'officer', 'organization.signatories');
        $first = $ticket->organization->signatories->first();

        $this->toSignatory($first, $ticket, $ticket->documents, $this->waiting($ticket->documents));
    }

    /**
     * One signatory acted on one or more papers of the same ticket ("Approve all"
     * sends them together), so the officer gets one message, not one per paper.
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
            $this->toOfficer($ticket, "✅ {$what} approved by {$by}. Next: {$next->pivot->title} ({$next->displayName()}).");
            $this->toSignatory($next, $ticket, $documents, $this->waiting($documents));

            return;
        }

        $this->toOfficer($ticket, ($action === 'return' ? "↩️ {$what} returned for revision" : "❌ {$what} rejected")
            ." by {$by}.\nRemarks: {$remarks}"
            .($action === 'return' ? "\nRevise it, then resubmit it on My Tickets." : "\nFile a new ticket if you still need it."));
    }

    public function resubmitted(Document $document): void
    {
        $ticket = $document->ticket->loadMissing('officer', 'organization.signatories');
        $signatory = $ticket->organization->signatories->firstWhere('pivot.step', $document->current_step);

        $this->toSignatory($signatory, $ticket, collect([$document]), 'Revised paper is back for your signature');
    }

    private function toOfficer(Ticket $ticket, string $line): void
    {
        $this->telegram->send($ticket->officer?->telegram_chat_id,
            "DOC-MON · {$ticket->code} · {$ticket->title}\n{$line}\n\n".url('/officer/tickets'));
    }

    /** @param  Collection<int, Document>  $documents */
    private function toSignatory(?User $signatory, Ticket $ticket, Collection $documents, string $line): void
    {
        $this->telegram->send($signatory?->telegram_chat_id,
            "📄 {$line}\n{$ticket->code} · {$ticket->title}\n"
            .$documents->map(fn (Document $d) => "• {$d->type}: {$d->title}")->implode("\n")
            ."\nFrom {$ticket->officer->displayName()} ({$ticket->organization->code})\n\n".url('/signatory/review'));
    }

    /**
     * "Paper “Budget Plan”" for one paper, "3 papers" for several.
     *
     * @param  Collection<int, Document>  $documents
     */
    private function papers(Collection $documents): string
    {
        $count = $documents->count();

        return $count === 1 ? "Paper “{$documents->first()->title}”" : "{$count} papers";
    }

    /** @param  Collection<int, Document>  $documents */
    private function waiting(Collection $documents): string
    {
        $count = $documents->count();

        return ($count === 1 ? 'A paper is' : "{$count} papers are").' waiting for your signature';
    }
}
