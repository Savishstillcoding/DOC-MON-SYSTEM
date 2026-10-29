<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

// role and status are left out on purpose: only the app (or an admin) may set
// them, so a sign-up form can't sneak in "role=admin" or "status=verified".
#[Fillable(['first_name', 'last_name', 'login_id', 'email', 'phone', 'position', 'organization_id', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** A signatory's possible statuses, shown on officers' ticket cards. */
    public const AVAILABILITY = ['Available', 'In a Meeting', 'In Class', 'Out of Office'];

    /** The officer positions that may file tickets. */
    public const OFFICER_POSITIONS = ['Secretary', 'Deputy Secretary'];

    /**
     * Same defaults as the database, so a brand-new user has them right away
     * (otherwise $user->role is empty until the row is reloaded).
     *
     * @var array<string, string>
     */
    protected $attributes = [
        'role' => 'officer',
        'status' => 'pending',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /** The organization an officer belongs to (signatories and admins have none). */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** Tickets this officer filed. */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'officer_id');
    }

    /** "Labastida, MJ Bryan", the way names are listed across DOC-MON. */
    public function displayName(): string
    {
        return trim("{$this->last_name}, {$this->first_name}", ', ');
    }

    public function initials(): string
    {
        return strtoupper(mb_substr($this->first_name ?? '', 0, 1).mb_substr($this->last_name ?? '', 0, 1));
    }

    /** The line under the name in the sidebar, e.g. "Secretary · DIGITS". */
    public function subtitle(): string
    {
        if ($this->role === 'admin') {
            return 'Administrator';
        }

        return collect([$this->position, $this->organization?->code])->filter()->implode(' · ');
    }
}
