<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
     * The ticket's overall status, worked out from its documents.
     * Uses the labels of <x-docmon.status-badge>.
     */
    public function status(): string
    {
        $statuses = $this->documents->pluck('status');

        return match (true) {
            $statuses->contains('returned') => 'Needs Revision',
            $statuses->every(fn ($s) => $s === 'approved') => 'Approved',
            $statuses->every(fn ($s) => $s === 'rejected') => 'Rejected',
            default => 'Pending',
        };
    }

    /**
     * The signatory who has the papers now: the one on the earliest step that
     * still has an unfinished document. Null when every document is done.
     */
    public function currentSignatory(): ?User
    {
        $step = $this->documents->whereIn('status', ['pending', 'returned'])->min('current_step');

        return $step ? $this->organization->signatories->firstWhere('pivot.step', $step) : null;
    }
}
