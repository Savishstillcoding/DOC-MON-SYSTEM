<?php

namespace App\Models;

/*
| READING GUIDE 7/15: DocumentAction
|
| WHAT: the model for the `document_actions` table. One row = one thing done
|       to a paper.
| USED FOR: each paper's History: who approved / returned / rejected /
|           resubmitted it, and when.
| KEY IDEA: rows are only added, never edited or deleted (proof of who did what).
*/

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * DocumentAction = one line of a paper's history: who did what, at which step, when.
 * Rows are only ever added, never changed or deleted. The database also refuses to
 * delete a paper or user that has history, so the record can't disappear.
 */
// FILLABLE = the fields our code fills in when it writes a history row.
// user_id (who did it) is left out on purpose: it's set separately to the person
// who is logged in, so nobody can record an action under someone else's name.
#[Fillable(['step', 'action', 'remarks'])]
class DocumentAction extends Model
{
    // The only values document_actions.action may hold
    public const APPROVED = 'approved';

    public const RETURNED = 'returned';

    public const REJECTED = 'rejected';

    public const RESUBMITTED = 'resubmitted';   // by the officer, after a return

    /** document() = the paper this history line belongs to. */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /** user() = who did it: the signatory who acted, or the officer who resubmitted. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
