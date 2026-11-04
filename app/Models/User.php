<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Notifications\ResetPasswordNotification;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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

    /**
     * Account statuses and how the admin pages show them. Only "verified" can log in.
     * "deactivated" = turned off by the admin (e.g. graduated); can be reactivated.
     */
    public const STATUS_LABELS = [
        'pending' => 'Unverified',
        'verified' => 'Verified',
        'rejected' => 'Rejected',
        'deactivated' => 'Deactivated',
    ];

    /** How each role is called on screen. */
    public const ROLE_LABELS = [
        'officer' => 'Student Officer',
        'signatory' => 'Signatory',
        'admin' => 'Administrator',
    ];

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
            'reviewed_at' => 'datetime',
        ];
    }

    /** The organization an officer belongs to (signatories and admins have none). */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * The organizations a signatory signs for, with their step and title in each
     * route (e.g. DIGITS, step 1, "Adviser"). The same person can be on several routes.
     */
    public function signatoryOrganizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organization_signatory')
            ->withPivot('step', 'title')
            ->withTimestamps()
            ->orderBy('code');
    }

    /** The ID photo uploaded at sign-up; gone once the admin has decided. */
    public function idDocument(): HasOne
    {
        return $this->hasOne(IdDocument::class);
    }

    /** The admin who verified or rejected this account. */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** Tickets this officer filed. */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'officer_id');
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst((string) $this->status);
    }

    public function roleLabel(): string
    {
        return self::ROLE_LABELS[$this->role] ?? ucfirst((string) $this->role);
    }

    /** "Forgot password?" sends DOC-MON's own wording of the reset email (AUD-008). */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
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

    /** The line under the name in the sidebar, e.g. "Secretary · DIGITS" or "Adviser · DIGITS". */
    public function subtitle(): string
    {
        if ($this->role === 'admin') {
            return 'Administrator';
        }

        // Officers belong to one organization; signatories can sign for several
        $organizations = $this->role === 'signatory'
            ? $this->signatoryOrganizations->pluck('code')->implode(', ')
            : $this->organization?->code;

        return collect([$this->position, $organizations])->filter()->implode(' · ');
    }
}
