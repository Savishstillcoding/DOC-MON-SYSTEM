<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of a document's permanent history: who did what, at which step.
 * Rows are only ever added, never changed or deleted (the database blocks
 * deleting a document or user that has actions).
 */
#[Fillable(['step', 'action', 'remarks'])]
class DocumentAction extends Model
{
    // The only values document_actions.action may hold
    public const APPROVED = 'approved';

    public const RETURNED = 'returned';

    public const REJECTED = 'rejected';

    public const RESUBMITTED = 'resubmitted';   // by the officer, after a return

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /** The signatory who acted, or the officer who resubmitted. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
