<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * One submission by an officer: a Resolution No. plus one document (Single)
 * or several (Batch). Single/Batch and the overall status are not stored;
 * they are worked out from the documents, so they can never disagree.
 */
// code, organization_id and officer_id are left out on purpose: the app sets
// them, never the form (an officer can't file a ticket for another organization)
#[Fillable(['ref_no', 'title', 'purpose', 'date_needed'])]
class Ticket extends Model
{
    protected function casts(): array
    {
        return [
            'date_needed' => 'date',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function officer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'officer_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /**
     * The next ticket code for this year: DM-2026-00001, DM-2026-00002, ...
     * The database's unique rule on `code` catches the rare case of two
     * officers submitting at the same moment (TicketController retries).
     */
    public static function nextCode(): string
    {
        $prefix = 'DM-'.now()->year.'-';
        $last = static::where('code', 'like', $prefix.'%')->max('code');
        $next = $last ? (int) substr($last, strlen($prefix)) + 1 : 1;

        return $prefix.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }

    /**
     * "Single" or "Batch (3)", counted from the documents.
     */
    public function typeLabel(): string
    {
        $count = $this->documents->count();

        return $count > 1 ? "Batch ({$count})" : 'Single';
    }

    /**
     * The status tabs on the officer's Dashboard and My Tickets, in order: every
     * label status() can return, after "All". One list, so the two pages can't
     * drift apart again (AUD-057: the Dashboard was missing "Partly Approved").
     */
    public const STATUS_TABS = ['All', 'Pending', 'Needs Revision', 'Approved', 'Partly Approved', 'Rejected'];

    /**
     * The ticket's overall status, worked out from its documents.
     * Uses the labels of <x-docmon.status-badge>. Order matters: anything
     * still to fix or sign wins over finished documents.
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

    /**
     * Where the unfinished papers are now (AUD-049). Each document moves on its own,
     * so a Batch can be split, e.g. one paper with the SSC President and one returned
     * to the officer by the Adviser. One entry per place, signatories first in route order:
     *   ['signatory' => User (with ->pivot), 'step' => 2, 'papers' => 1, 'with_officer' => false]
     * with_officer = returned: the officer has it to revise, then gives it back to that signatory.
     * Empty when every document is finished.
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
