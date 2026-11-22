<?php

namespace App\Models;

/*
| READING GUIDE 4/15: Organization
|
| WHAT: the model for the `organizations` table (e.g. DIGITS).
| USED FOR: an org's officers, its tickets, and its approval route (who signs,
|           in what order).
| KEY IDEA: the route is saved as data, so the admin can change it without changing the code.
*/

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Organization = a student organization, e.g. DIGITS. */
// FILLABLE = the fields the admin's organization form may fill in:
// the short code (e.g. DIGITS) and the full name. Nothing else can be set from the form.
#[Fillable(['code', 'name'])]
class Organization extends Model
{
    /**
     * signatories() = the org's approval route: its signatories, step 1 first.
     * The step and title (e.g. 1, "Adviser") are saved in a link table
     * (organization_signatory), because one person can sign for several orgs.
     */
    public function signatories(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_signatory')
            ->withPivot('step', 'title')
            ->withTimestamps()
            ->orderByPivot('step');
    }

    /** officers() = the student officers of this org. */
    public function officers(): HasMany
    {
        return $this->hasMany(User::class)->where('role', 'officer');
    }

    /** tickets() = every ticket filed by this org. */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }
}
