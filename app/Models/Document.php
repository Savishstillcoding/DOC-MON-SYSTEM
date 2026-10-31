<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

/**
 * One physical paper inside a ticket. Each document is approved separately,
 * so it has its own status and current step.
 */
// status and current_step are left out on purpose: a new document always starts
// pending at step 1 (database defaults), and only signatory actions move it
#[Fillable(['type', 'title', 'ref_no'])]
class Document extends Model
{
    /**
     * The documents student organizations submit (confirmed list, 2026-10-22).
     * "Other (specify)" is the fallback: the document title then says what it is.
     */
    public const TYPES = [
        'Resolution',
        'Activity Proposal',
        'Budgetary',
        'Narrative/Accomplishment Report',
        'Sem-End Report',
        'Year-End Report',
        'Other (specify)',
    ];

    // The only values documents.status may hold. Use these constants instead of
    // typing the words, so a typo can't create a status nothing else understands.
    public const PENDING = 'pending';     // waiting for the signatory at current_step

    public const RETURNED = 'returned';   // sent back to the officer; returns to the same step

    public const APPROVED = 'approved';   // the last signatory approved it (final)

    public const REJECTED = 'rejected';   // closed; the officer files a new ticket (final)

    /** What officers and signatories see for each status (labels of <x-docmon.status-badge>). */
    public const STATUS_LABELS = [
        self::PENDING => 'Pending',
        self::RETURNED => 'Needs Revision',
        self::APPROVED => 'Approved',
        self::REJECTED => 'Rejected',
    ];

    // Any change to a document also bumps its ticket's updated_at ("Last Update")
    protected $touches = ['ticket'];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /** Permanent history: every approve, return, reject and resubmit. */
    public function actions(): HasMany
    {
        return $this->hasMany(DocumentAction::class);
    }

    /** The newest action, e.g. to show why the document was returned. */
    public function latestAction(): HasOne
    {
        return $this->hasOne(DocumentAction::class)->latestOfMany();
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? 'Pending';
    }

    /**
     * Documents sitting at this signatory's step: the signatory is on the route of
     * the ticket's organization, at exactly the step the document has reached.
     * Combine with a status, e.g. ->where('status', Document::PENDING).
     */
    public function scopeAtStepOf(Builder $query, User $signatory): void
    {
        $query->whereHas('ticket', fn (Builder $ticket) => $ticket->whereExists(fn ($route) => $route
            ->from('organization_signatory')
            ->whereColumn('organization_signatory.organization_id', 'tickets.organization_id')
            ->whereColumn('organization_signatory.step', 'documents.current_step')
            ->where('organization_signatory.user_id', $signatory->id)));
    }

    /**
     * Can this signatory act on the document right now?
     * Only if it is pending and they are the signatory at its current step.
     */
    public function isWaitingFor(User $signatory): bool
    {
        return $this->status === self::PENDING
            && static::whereKey($this->id)->atStepOf($signatory)->exists();
    }

    /**
     * A signatory approves, returns, or rejects this document, and the action is
     * recorded permanently. Returns false (and changes nothing) when the document
     * is not waiting for this signatory.
     *
     * Call it inside DB::transaction() on a row loaded with lockForUpdate(), so two
     * clicks at the same moment can't both act on the same document.
     *
     * @param  'approve'|'return'|'reject'  $action
     */
    public function applyAction(User $signatory, string $action, ?string $remarks = null): bool
    {
        if (! $this->isWaitingFor($signatory)) {
            return false;
        }

        $step = $this->current_step;

        if ($action === 'approve') {
            // Next signatory, or Approved for good after the last one
            $next = $this->nextStep();
            $next === null ? $this->status = self::APPROVED : $this->current_step = $next;
        } else {
            // A returned document keeps its step, so it comes back to this signatory
            $this->status = $action === 'return' ? self::RETURNED : self::REJECTED;
        }

        $this->save();
        $this->actions()->make([
            'step' => $step,
            'action' => [
                'approve' => DocumentAction::APPROVED,
                'return' => DocumentAction::RETURNED,
                'reject' => DocumentAction::REJECTED,
            ][$action],
            'remarks' => $remarks,
        ])->user()->associate($signatory)->save();

        return true;
    }

    /**
     * The step after the current one in the organization's route, or null when
     * the current signatory is the last one (their approval is final).
     */
    public function nextStep(): ?int
    {
        $next = DB::table('organization_signatory')
            ->where('organization_id', $this->ticket->organization_id)
            ->where('step', '>', $this->current_step)
            ->min('step');

        return $next === null ? null : (int) $next;
    }
}
