<?php

namespace App\Models;

/*
| READING GUIDE 6/15: Document
|
| WHAT: the model for the `documents` table. One row = one physical paper.
| USED FOR: tracking where each paper is (current_step) and its status, and
|           moving it with applyAction().
| KEY IDEA: applyAction() is the rulebook:
|             approve → next signatory (or Approved after the last one)
|             return  → back to the officer, then to the SAME signatory
|             reject  → closed for good
|           Only the signatory at the paper's current step can act.
*/

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Document = one physical paper inside a ticket.
 * Each paper is approved on its own, so it has its own status and current step.
 */
// FILLABLE = the fields the New Ticket form may fill in for each paper.
// How it works:
//   1. The officer adds papers in the New Ticket form.
//   2. Laravel copies ONLY type and title into each paper.
//   3. Anything else the form sends is thrown away.
// status and current_step are left out on purpose: every new paper starts as pending
// at step 1, and only a signatory's Approve / Return / Reject (applyAction) moves it.
// ref_no is left out too: the ticket's Ref No. is the one number. The column stays only
// so papers filed before this change still show the number they were given.
#[Fillable(['type', 'title'])]
class Document extends Model
{
    /**
     * TYPES = the kinds of papers an officer can choose in the New Ticket form.
     * "Other (specify)" is for anything else; the paper's title then says what it is.
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

    // The only 4 values documents.status can have. The code writes Document::PENDING
    // instead of typing 'pending', so a typo (like 'pendng') becomes an error right away
    // instead of a status that nothing understands.
    public const PENDING = 'pending';     // waiting for the signatory at current_step

    public const RETURNED = 'returned';   // sent back to the officer to fix; then goes back to the SAME signatory

    public const APPROVED = 'approved';   // the last signatory approved it (finished)

    public const REJECTED = 'rejected';   // closed for good; the officer files a new ticket (finished)

    /** STATUS_LABELS = the word shown on the coloured badge for each status ("returned" → "Needs Revision"). */
    public const STATUS_LABELS = [
        self::PENDING => 'Pending',
        self::RETURNED => 'Needs Revision',
        self::APPROVED => 'Approved',
        self::REJECTED => 'Rejected',
    ];

    // $touches: whenever a paper changes, its ticket's "Last Update" time (updated_at) changes too
    protected $touches = ['ticket'];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /** actions() = this paper's history rows: every approve, return, reject and resubmit. */
    public function actions(): HasMany
    {
        return $this->hasMany(DocumentAction::class);
    }

    /** latestAction() = the newest history row, e.g. to show WHY it was returned. */
    public function latestAction(): HasOne
    {
        return $this->hasOne(DocumentAction::class)->latestOfMany();
    }

    /**
     * timeline() = this paper's story for the officer's History fold-out.
     * How it works:
     *   1. Start with "Submitted".
     *   2. Add every approve / return / reject / resubmit: who, when, and the remark.
     *   3. End with where it is now ("Now waiting for the SSC President to sign").
     *
     * @param  Collection<int, User>  $route  the organization's signatories, with ->pivot (step, title)
     * @return list<array{tone: string, text: string, who: ?string, remark: ?string, at: ?Carbon}>
     */
    public function timeline(Collection $route, string $filedBy): array
    {
        $title = fn (int $step) => $route->firstWhere('pivot.step', $step)?->pivot->title ?? "step {$step}";
        $last = $this->actions->last();

        $events = [['tone' => 'dark', 'text' => 'Submitted', 'who' => $filedBy, 'remark' => null, 'at' => $this->created_at]];

        foreach ($this->actions as $action) {
            [$tone, $text] = match ($action->action) {
                DocumentAction::APPROVED => ['green', "Approved by {$title($action->step)}"
                    .($action === $last && $this->status === self::APPROVED ? ' (final signature)' : '')],
                DocumentAction::RETURNED => ['blue', "Returned for revision by {$title($action->step)}"],
                DocumentAction::REJECTED => ['red', "Rejected by {$title($action->step)}"],
                default => ['dark', "Resubmitted to {$title($action->step)}"],
            };
            $events[] = ['tone' => $tone, 'text' => $text, 'who' => $action->user?->displayName(),
                'remark' => $action->remarks, 'at' => $action->created_at];
        }

        // Where it is now; nothing to add once it's finished (approved or rejected)
        $holder = $route->firstWhere('pivot.step', $this->current_step);
        if ($this->status === self::PENDING) {
            $events[] = ['tone' => 'now', 'text' => "Now waiting for {$title($this->current_step)} to sign",
                'who' => $holder?->displayName(), 'remark' => null, 'at' => null];
        } elseif ($this->status === self::RETURNED) {
            $events[] = ['tone' => 'now', 'text' => "Now with you: revise it, then resubmit it to {$title($this->current_step)}",
                'who' => null, 'remark' => null, 'at' => null];
        }

        return $events;
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? 'Pending';
    }

    /**
     * atStepOf() = a search filter: the papers sitting at THIS signatory's step.
     * A paper matches if, in its organization's route, this signatory is at the
     * step the paper has reached. Used as Document::atStepOf($signatory).
     */
    public function scopeAtStepOf(Builder $query, User $signatory): void
    {
        // In words: keep a document only if the route table has a row with
        // the ticket's organization + the document's current step + this signatory.
        $query->whereHas('ticket', fn (Builder $ticket) => $ticket->whereExists(fn ($route) => $route
            ->from('organization_signatory')
            ->whereColumn('organization_signatory.organization_id', 'tickets.organization_id')
            ->whereColumn('organization_signatory.step', 'documents.current_step')
            ->where('organization_signatory.user_id', $signatory->id)));
    }

    /**
     * isWaitingFor() = the bouncer: may this signatory act on this paper right now?
     * YES only if the paper is pending AND this signatory is at its current step.
     */
    public function isWaitingFor(User $signatory): bool
    {
        return $this->status === self::PENDING
            && static::whereKey($this->id)->atStepOf($signatory)->exists();
    }

    /**
     * applyAction() = what happens when a signatory clicks Approve, Return or Reject.
     * How it works:
     *   1. Check isWaitingFor(). Not their turn? Stop, change nothing (returns false).
     *   2. Move the paper:
     *        approve → next signatory's step, or "approved" after the last one
     *        return  → "returned" (stays at the same step, comes back to them)
     *        reject  → "rejected" (closed for good)
     *   3. Save a history row (who, what, which step, remarks).
     *   4. If rejected: maybe free the ticket's Ref No. (see below).
     *   5. Write it in the admin's Activity Log.
     * The controller runs this with the paper LOCKED, so a double click can't act twice.
     *
     * @param  'approve'|'return'|'reject'  $action
     */
    public function applyAction(User $signatory, string $action, ?string $remarks = null): bool
    {
        // 1. Bouncer: not your turn, or already handled? Change nothing.
        if (! $this->isWaitingFor($signatory)) {
            return false;
        }

        // Remember the step BEFORE moving, for the history row
        $step = $this->current_step;

        // 2. Move the paper
        if ($action === 'approve') {
            // Is there a next signatory? Yes: move to their step. No (this was the last
            // one, the VPSD): the paper is Approved for good.
            // (Read "a ? b : c" as "if a then b, else c".)
            $next = $this->nextStep();
            $next === null ? $this->status = self::APPROVED : $this->current_step = $next;
        } else {
            // Return or reject. current_step does NOT change, so a returned paper
            // comes back to this same signatory after the officer fixes it
            $this->status = $action === 'return' ? self::RETURNED : self::REJECTED;
        }

        $this->save();

        // 3. Permanent history: build a new DocumentAction for this paper, set who
        //    did it (associate = fill in user_id), and save it
        $this->actions()->make([
            'step' => $step,
            'action' => [
                'approve' => DocumentAction::APPROVED,
                'return' => DocumentAction::RETURNED,
                'reject' => DocumentAction::REJECTED,
            ][$action],
            'remarks' => $remarks,
        ])->user()->associate($signatory)->save();

        // 4. A rejection may free the ticket's Ref No. (see below)
        if ($action === 'reject') {
            $this->releaseRefNoIfTicketRejected();
        }

        // 5. The admin's Activity Log
        $this->loadMissing('ticket');
        ActivityLog::record($signatory, 'tickets',
            ['approve' => $this->status === self::APPROVED ? 'Approved document (final)' : 'Approved document',
                'return' => 'Returned document', 'reject' => 'Rejected document'][$action],
            "{$this->ticket->code} · {$this->title} · step {$step}".($remarks ? " · {$remarks}" : ''));

        return true;
    }

    /**
     * releaseRefNoIfTicketRejected() = frees the Ref No. when the whole ticket is rejected.
     * How it works:
     *   1. Lock the ticket (so two signatories rejecting at once take turns).
     *   2. Are ALL its papers rejected now?
     *   3. Yes → empty active_ref_no, so the same Ref No. can be filed again.
     */
    private function releaseRefNoIfTicketRejected(): void
    {
        $ticket = Ticket::whereKey($this->ticket_id)->lockForUpdate()->first();

        if ($ticket->documents()->where('status', '!=', self::REJECTED)->doesntExist()) {
            $ticket->forceFill(['active_ref_no' => null])->save();
            $this->setRelation('ticket', $ticket);
        }
    }

    /**
     * nextStep() = the next step in the organization's route
     * (e.g. 1 → 2), or nothing if this was the last signatory (their approval is final).
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
