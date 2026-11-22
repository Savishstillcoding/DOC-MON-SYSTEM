<?php

namespace App\Models;

/*
| READING GUIDE 3/15: User
|
| WHAT: the model for the `users` table. One row = one account.
| USED FOR: every account: officers, signatories and the admin (the `role`
|           column says which).
| KEY IDEA: role = what you are; status = can you log in (only "verified" can).
|           role and status are NOT fillable, so a sign-up form can't make itself an admin.
|           Passwords are saved as hashes, never as readable text.
*/

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Notifications\ResetPasswordNotification;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

// FILLABLE = the fields a form is allowed to fill in.
// How it works:
//   1. Someone sends the sign-up form.
//   2. Laravel copies ONLY the fields in this list into the new account.
//   3. Anything else the form sends (like "role=admin") is thrown away.
// role and status are left out on purpose: only our code sets them, so nobody can
// make themselves an admin or skip the admin's verification.
// HIDDEN = never shown when the account is turned into data (the password hash).
#[Fillable(['first_name', 'last_name', 'login_id', 'email', 'phone', 'position', 'organization_id', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * AVAILABILITY = the statuses a signatory can pick, and each one's badge colour.
     * How it works:
     *   1. The signatory picks one on their page (the dropdown shows this list).
     *   2. The server checks the choice is in this list.
     *   3. Officers see it as a badge: green = here, amber = busy, red = away.
     * One list for all three, so they always match.
     */
    public const AVAILABILITY = [
        'Available' => 'available',
        'In a Meeting' => 'busy',
        'In Class' => 'busy',
        'On Lunch Break' => 'busy',
        'On Leave' => 'away',
        'Out of Office' => 'away',
    ];

    /**
     * STATUS_LABELS = the 4 account statuses, and the word shown on screen for each.
     * How an account moves:
     *   1. pending:     just signed up, waiting for the admin ("Unverified")
     *   2. verified:    the admin checked the ID; the ONLY status that can log in
     *      or rejected: the admin said no, with a reason
     *   3. deactivated: the admin turned it off later (e.g. graduated); can be turned back on
     */
    public const STATUS_LABELS = [
        'pending' => 'Unverified',
        'verified' => 'Verified',
        'rejected' => 'Rejected',
        'deactivated' => 'Deactivated',
    ];

    /** ROLE_LABELS = the name shown on screen for each role (officer → "Student Officer"). */
    public const ROLE_LABELS = [
        'officer' => 'Student Officer',
        'signatory' => 'Signatory',
        'admin' => 'Administrator',
    ];

    /**
     * RETENTION_YEARS = how long we keep an account before the admin reviews it.
     * After 4 years (e.g. the student graduated) the admin checks if it's still needed,
     * as the privacy notice promises.
     */
    public const RETENTION_YEARS = 4;

    /** OFFICER_POSITIONS = the only officer positions that can sign up and file tickets. */
    public const OFFICER_POSITIONS = ['Secretary', 'Deputy Secretary'];

    /**
     * $attributes = the starting values of a brand-new account:
     * every new account starts as an officer that is pending (not yet verified).
     *
     * @var array<string, string>
     */
    protected $attributes = [
        'role' => 'officer',
        'status' => 'pending',
    ];

    /**
     * casts() = automatic conversions when a column is saved or read.
     * How it works:
     *   - 'hashed': the password is scrambled (bcrypt) BEFORE it is saved, so the
     *     database never holds the real password.
     *   - 'datetime': the column becomes a real date, so it can be shown as "Nov 12".
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'reviewed_at' => 'datetime',
            'privacy_accepted_at' => 'datetime',
            'availability_updated_at' => 'datetime',
        ];
    }

    /**
     * availabilitySetAt() = WHEN the signatory last set their status, written short.
     * How it works:
     *   - set today      → "9:14 AM"
     *   - set yesterday  → "yesterday, 4:30 PM"
     *   - older          → "Nov 12"
     *   - never set      → nothing
     */
    public function availabilitySetAt(): ?string
    {
        $set = $this->availability_updated_at;
        // \u{00A0} is a space that never breaks a line, so "9:14" and "AM" always stay together
        $time = fn () => $set->format('g:i')."\u{00A0}".$set->format('A');

        return match (true) {
            $set === null => null,
            $set->isToday() => $time(),
            $set->isYesterday() => 'yesterday, '.$time(),
            default => $set->format('M j'),
        };
    }

    /**
     * availabilityNote() = the small text after a signatory's status.
     * How it works:
     *   1. Available? Show nothing.
     *   2. Otherwise join the "back at" time and when it was set:
     *      "Back at 2:00 PM · set 9:14 AM"
     * Why: the "set" time shows how old the status is, so nobody trusts
     * "Back at 2:00 PM" at 5 PM.
     */
    public function availabilityNote(): ?string
    {
        if (($this->availability ?? 'Available') === 'Available') {
            return null;
        }

        $set = $this->availabilitySetAt();

        return collect([$this->back_at ? "Back at {$this->back_at}" : null, $set ? "set {$set}" : null])
            ->filter()->implode(' · ') ?: null;
    }

    /*
     * RELATIONSHIPS: how an account connects to other tables.
     * Each one lets the code write $user->organization, $user->tickets, ...
     * instead of writing a database query by hand.
     */

    /** organization() = the org an OFFICER belongs to (e.g. DIGITS). */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * signatoryOrganizations() = the orgs a SIGNATORY signs for, with their step and
     * title in each (e.g. DIGITS, step 1, "Adviser"). One person can sign for several orgs.
     */
    public function signatoryOrganizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organization_signatory')
            ->withPivot('step', 'title')
            ->withTimestamps()
            ->orderBy('code');
    }

    /** idDocument() = the ID photo from sign-up. Deleted once the admin decides. */
    public function idDocument(): HasOne
    {
        return $this->hasOne(IdDocument::class);
    }

    /** reviewer() = the admin who verified or rejected this account. */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** tickets() = the tickets this officer filed. */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'officer_id');
    }

    /** statusLabel() = the account status in screen words ("pending" → "Unverified"). */
    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst((string) $this->status);
    }

    /** roleLabel() = the role in screen words ("officer" → "Student Officer"). */
    public function roleLabel(): string
    {
        return self::ROLE_LABELS[$this->role] ?? ucfirst((string) $this->role);
    }

    /**
     * pastRetention() = a search filter: verified officer and signatory accounts
     * older than 4 years, for the admin's yearly clean-up.
     * Used as User::pastRetention()->get().
     */
    public function scopePastRetention(Builder $query): void
    {
        $query->where('status', 'verified')
            ->whereIn('role', ['officer', 'signatory'])
            ->where('created_at', '<=', now()->subYears(self::RETENTION_YEARS));
    }

    /**
     * sendPasswordResetNotification() = sends the "Forgot password?" email.
     * It uses OUR version (DOC-MON's wording) instead of Laravel's default.
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    /** displayName() = the name as shown everywhere: "Labastida, MJ Bryan". */
    public function displayName(): string
    {
        return trim("{$this->last_name}, {$this->first_name}", ', ');
    }

    /** initials() = first letters of first + last name, for the round avatar ("ML"). */
    public function initials(): string
    {
        return strtoupper(mb_substr($this->first_name ?? '', 0, 1).mb_substr($this->last_name ?? '', 0, 1));
    }

    /**
     * subtitle() = the line under the name in the sidebar.
     *   admin → "Administrator"; officer → "Secretary · DIGITS"; signatory → "Adviser · DIGITS"
     */
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
