<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }
}
