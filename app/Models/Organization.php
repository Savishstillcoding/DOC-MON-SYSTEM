<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A student organization, e.g. DIGITS.
 */
#[Fillable(['code', 'name'])]
class Organization extends Model
{
    /**
     * The organization's approval route: its signatories, first step first.
     * The step and title live on the link table (organization_signatory), because
     * the same person can sign for several organizations at different steps.
     */
    public function signatories(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_signatory')
            ->withPivot('step', 'title')
            ->withTimestamps()
            ->orderByPivot('step');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }
}
