<?php

namespace App\Http\Controllers\Officer;

use App\Http\Controllers\Controller;
use App\Models\DocumentAction;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * The officer's dashboard (AUD-038): their real tickets and numbers, the live
 * availability of their organization's signatories, and what happened recently.
 */
class DashboardController extends Controller
{
    /** The status tabs above the ticket cards (the full list is on My Tickets). */
    private const TABS = ['All', 'Pending', 'Needs Revision', 'Approved', 'Rejected'];

    /** How many ticket cards the dashboard shows. */
    private const CARDS = 4;

    public function index(Request $request): View
    {
        $officer = $request->user();
        $organization = $officer->organization?->load('signatories');

        $tickets = $officer->tickets()
            ->with(['documents', 'organization.signatories'])
            ->latest()->latest('id')
            ->get();

        $byStatus = $tickets->countBy(fn (Ticket $t) => $t->status());

        $tab = in_array($request->query('status'), self::TABS, true) ? $request->query('status') : 'All';
        $shown = $tab === 'All' ? $tickets : $tickets->filter(fn (Ticket $t) => $t->status() === $tab);

        return view('student-officer.dashboard', [
            'organization' => $organization,
            'stats' => [
                'total' => $tickets->count(),
                'pending' => $byStatus['Pending'] ?? 0,
                'approved' => $byStatus['Approved'] ?? 0,
                'revision' => $byStatus['Needs Revision'] ?? 0,
                'rejected' => $byStatus['Rejected'] ?? 0,
            ],
            'tab' => $tab,
            'cards' => $shown->take(self::CARDS)->map(fn (Ticket $t) => $this->card($t, $officer->displayName()))->values(),
            'activity' => $this->activity($officer->id, $tickets),
        ]);
    }

    /** The array <x-docmon.ticket-card> expects, built from a real ticket. */
    private function card(Ticket $ticket, string $officerName): array
    {
        $holders = $ticket->holders();
        // All unfinished papers in one place: the card looks like always (one signatory + their dot)
        $signatory = $holders->count() === 1 ? $holders[0]['signatory'] : null;
        $count = $ticket->documents->count();

        return [
            'code' => $ticket->code,
            'ref' => $ticket->ref_no,
            'status' => $ticket->status(),
            'title' => $ticket->title,
            'type' => $count > 1 ? "Batch · {$count} documents" : 'Single',
            'officer' => $officerName,
            'signatory' => $signatory ? "{$signatory->displayName()} ({$signatory->pivot->title})" : 'Done: no signatory left',
            // Live availability of whoever holds the papers now (none when the ticket is finished)
            'availability' => $signatory ? ($signatory->availability ?? 'Available') : null,
            'availability_note' => $signatory?->back_at ? "Back at {$signatory->back_at}" : null,
            // A split Batch (AUD-049): one line per place, each signatory with their own dot
            'holders' => $holders->count() > 1 ? $holders->map(fn (array $h) => $this->holderLine($h))->all() : [],
            'submitted' => $ticket->created_at->format('M j, Y'),
            'updated' => $ticket->updated_at->format('M j, g:i A'),
        ];
    }

    /** One line of a split Batch's card: who has how many papers, and their availability. */
    private function holderLine(array $holder): array
    {
        $s = $holder['signatory'];
        $papers = $holder['papers'].' '.str('paper')->plural($holder['papers']);

        return $holder['with_officer']
            ? ['text' => "Back with you: {$papers} to revise, then to {$s?->pivot->title}", 'availability' => null, 'note' => null]
            : ['text' => "With {$s?->displayName()} ({$s?->pivot->title}): {$papers}",
                'availability' => $s?->availability ?? 'Available', 'note' => $s?->back_at ? "Back at {$s->back_at}" : null];
    }

    /**
     * The latest 5 events on this officer's tickets: submissions plus every
     * approve, return, reject and resubmit, newest first.
     *
     * @param  Collection<int, Ticket>  $tickets
     */
    private function activity(int $officerId, Collection $tickets): Collection
    {
        $submitted = $tickets->take(5)->map(fn (Ticket $t) => [
            'color' => 'dark',
            'text' => "{$t->code} submitted to ".($t->organization->signatories->first()?->pivot->title ?? 'the first signatory'),
            'at' => $t->created_at,
        ]);

        $actions = DocumentAction::whereHas('document.ticket', fn ($q) => $q->where('officer_id', $officerId))
            ->with('document.ticket.organization.signatories')
            ->latest()->latest('id')->limit(5)->get()
            ->map(function (DocumentAction $a) {
                $ticket = $a->document->ticket;
                $title = $ticket->organization->signatories->firstWhere('pivot.step', $a->step)?->pivot->title ?? 'a signatory';

                [$color, $text] = match ($a->action) {
                    DocumentAction::APPROVED => ['green', "approved by {$title}"],
                    DocumentAction::RETURNED => ['blue', "returned for revision by {$title}"],
                    DocumentAction::REJECTED => ['red', "rejected by {$title}"],
                    default => ['dark', "resubmitted to {$title}"],
                };

                return ['color' => $color, 'text' => "{$ticket->code} · {$a->document->title} {$text}", 'at' => $a->created_at];
            });

        return $actions->concat($submitted)->sortByDesc('at')->take(5)->values();
    }
}
