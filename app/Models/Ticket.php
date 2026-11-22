<?php

namespace App\Models;

/*
| READING GUIDE 5/15: Ticket
|
| WHAT: the model for the `tickets` table. One row = one submission.
| USED FOR: grouping papers under one Ref No.: 1 paper = Single, more = Batch.
| KEY IDEA: the ticket's status is worked out from its papers, never saved,
|           so the two can never disagree.
*/

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * Ticket = one submission by an officer: a Ref No. (Resolution No.) plus its papers.
 * 1 paper = Single, more = Batch. Single/Batch and the overall status are NOT saved;
 * they are worked out from the papers every time, so they can never disagree.
 */
// FILLABLE = the fields the New Ticket form is allowed to fill in.
// How it works:
//   1. The officer sends the New Ticket form.
//   2. Laravel copies ONLY the fields in this list into the ticket.
//   3. Anything else the form sends is thrown away.
// code, organization_id and officer_id are left out on purpose: our code fills them in
// from the logged-in officer, so nobody can file a ticket for another organization.
#[Fillable(['ref_no', 'title', 'purpose', 'date_needed'])]
class Ticket extends Model
{
    /** casts() = date_needed becomes a real date, so it can be compared with today. */
    protected function casts(): array
    {
        return [
            'date_needed' => 'date',
        ];
    }

    /**
     * booted() = runs automatically when a new ticket is created.
     * How it works:
     *   1. The Ref No. is copied into active_ref_no.
     *   2. The database allows each active_ref_no only ONCE per organization,
     *      so two open tickets can't share a Ref No.
     *   3. If every paper gets rejected, active_ref_no is emptied, so the same
     *      number can be filed again.
     */
    protected static function booted(): void
    {
        static::creating(fn (Ticket $ticket) => $ticket->active_ref_no ??= $ticket->ref_no);
    }

    /** organization() = the org this ticket belongs to. */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** officer() = the officer who filed it. */
    public function officer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'officer_id');
    }

    /** documents() = the papers inside this ticket. */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /**
     * visibleTo() = a search filter: which tickets an officer can see.
     * Answer: ALL tickets of their own organization (not only the ones they filed),
     * so the Deputy Secretary can take over when the Secretary is away.
     * Other organizations' tickets are never shown.
     * Used as Ticket::visibleTo($officer).
     */
    public function scopeVisibleTo(Builder $query, User $officer): void
    {
        $query->where('organization_id', $officer->organization_id);
    }

    /** isVisibleTo() = the same rule for ONE ticket: yes/no, may this officer work on it? */
    public function isVisibleTo(User $officer): bool
    {
        return $officer->organization_id !== null
            && (int) $this->organization_id === (int) $officer->organization_id;
    }

    /**
     * search() = the search box on My Tickets and For Review.
     * How it works: a ticket is shown if the typed words appear in ANY of these:
     *   - the ticket's code, Ref No. or title
     *   - one of its papers' title or Ref No.
     *   - the name of the officer who filed it
     * Example: Ticket::search('budget')->get()
     */
    public function scopeSearch(Builder $query, string $term): void
    {
        // % means "anything before or after", so 'budget' also finds "2026 Budget Plan"
        $like = '%'.$term.'%';

        // All the ORs are wrapped in ONE bracket. Without it, "my organization AND code
        // matches OR title matches" would also show other organizations' tickets.
        $query->where(fn (Builder $q) => $q
            ->where('code', 'like', $like)
            ->orWhere('ref_no', 'like', $like)
            ->orWhere('title', 'like', $like)
            ->orWhereHas('documents', fn (Builder $d) => $d->where(fn (Builder $dw) => $dw
                ->where('title', 'like', $like)
                ->orWhere('ref_no', 'like', $like)))
            ->orWhereHas('officer', fn (Builder $o) => $o->where(fn (Builder $ow) => $ow
                ->where('first_name', 'like', $like)
                ->orWhere('last_name', 'like', $like))));
    }

    /**
     * nextCode() = makes the next ticket code: DM-2026-00001, DM-2026-00002, ...
     * How it works:
     *   1. Find this year's highest code (e.g. DM-2026-00007).
     *   2. Add 1 → DM-2026-00008.
     *   3. If two officers get the same code at the same moment, the database refuses
     *      the second one, and TicketController tries again with a new code.
     */
    public static function nextCode(): string
    {
        $prefix = 'DM-'.now()->year.'-';
        $last = static::where('code', 'like', $prefix.'%')->max('code');
        $next = $last ? (int) substr($last, strlen($prefix)) + 1 : 1;

        return $prefix.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }

    /** typeLabel() = counts the papers: 1 → "Single", 3 → "Batch (3)". */
    public function typeLabel(): string
    {
        $count = $this->documents->count();

        return $count > 1 ? "Batch ({$count})" : 'Single';
    }

    /**
     * STATUS_TABS = the status tabs on the officer's Dashboard and My Tickets.
     * Both pages read this one list, so they always show the same tabs.
     */
    public const STATUS_TABS = ['All', 'Pending', 'Needs Revision', 'Approved', 'Partly Approved', 'Rejected'];

    /**
     * status() = the ticket's overall status, worked out from its papers.
     * How it works (checked top to bottom, the first match wins):
     *   any paper returned?      → Needs Revision
     *   any paper still waiting? → Pending
     *   all approved?            → Approved
     *   all rejected?            → Rejected
     *   otherwise                → Partly Approved (a Batch: some passed, some rejected)
     * The order matters: unfinished work always shows first.
     */
    public function status(): string
    {
        $statuses = $this->documents->pluck('status');

        return match (true) {
            $statuses->contains(Document::RETURNED) => 'Needs Revision',
            $statuses->contains(Document::PENDING) => 'Pending',
            $statuses->every(fn ($s) => $s === Document::APPROVED) => 'Approved',
            $statuses->every(fn ($s) => $s === Document::REJECTED) => 'Rejected',
            // A finished Batch where some papers passed and some were rejected
            default => 'Partly Approved',
        };
    }

    /** DUE_SOON_DAYS = a Date Needed 3 days away or less shows as "Due Soon". */
    public const DUE_SOON_DAYS = 3;

    /**
     * deadlineStatus() = the deadline badge on a ticket.
     * How it works:
     *   1. No Date Needed, or every paper is finished? → no badge.
     *   2. Count the days from today to the Date Needed.
     *   3. Below 0 → "Overdue"; 3 or less → "Due Soon"; more → no badge.
     * Worked out from today's date every time, so it's never out of date.
     */
    public function deadlineStatus(): ?string
    {
        if ($this->date_needed === null || ! in_array($this->status(), ['Pending', 'Needs Revision'], true)) {
            return null;
        }

        $daysLeft = (int) today()->diffInDays($this->date_needed, false);

        return match (true) {
            $daysLeft < 0 => 'Overdue',
            $daysLeft <= self::DUE_SOON_DAYS => 'Due Soon',
            default => null,
        };
    }

    /**
     * urgencyKey() = used to sort a signatory's list of papers.
     * Soonest Date Needed first (so overdue papers are on top), then tickets with
     * no date.
     */
    public function urgencyKey(): string
    {
        return $this->date_needed ? '1|'.$this->date_needed->toDateString() : '2|';
    }

    /**
     * holders() = where the unfinished papers are right now
     * (the "Waiting for ..." line on a ticket card).
     * How it works:
     *   1. Take the papers that are still pending or returned.
     *   2. Group them by place: which signatory's step, or back with the officer.
     *   3. Give one entry per place, e.g. "2 papers waiting for the SSC President".
     * Each paper moves on its own, so a Batch can be in two places at once.
     * with_officer = true means returned: the officer fixes it, then gives it back
     * to the same signatory.
     *
     * @return Collection<int, array{signatory: ?User, step: int, papers: int, with_officer: bool}>
     */
    public function holders(): Collection
    {
        $route = $this->organization->signatories;

        return $this->documents
            ->whereIn('status', [Document::PENDING, Document::RETURNED])
            ->groupBy(fn (Document $d) => $d->status.'@'.$d->current_step)
            ->map(fn (Collection $papers) => [
                'signatory' => $route->firstWhere('pivot.step', $papers->first()->current_step),
                'step' => (int) $papers->first()->current_step,
                'papers' => $papers->count(),
                'with_officer' => $papers->first()->status === Document::RETURNED,
            ])
            ->sortBy([['with_officer', 'asc'], ['step', 'asc']])
            ->values();
    }
}
