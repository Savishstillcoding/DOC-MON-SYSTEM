<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The ID photo or PDF a user uploaded at sign-up (stored in the database, AUD-012).
 * Only the admin can open it, and it is deleted once the account is verified or rejected.
 */
class IdDocument extends Model
{
    /** The file types accepted at sign-up (checked by content, not by file name). */
    public const MIMES = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];

    /** Upload limit in kilobytes (2 MB), same as the browser check in register.js. */
    public const MAX_KB = 2048;

    // The 2 MB file never goes into JSON or logs by accident
    protected $hidden = ['data'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
