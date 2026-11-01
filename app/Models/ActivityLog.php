<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * One line of the Activity Logs: who did what, and when. Write-only: a saved
 * line can never be changed or deleted from the app, so the record can be trusted.
 *
 * Usage: ActivityLog::record($user, 'tickets', 'Submitted ticket', "DM-2026-00001 · DIGITS Week");
 */
class ActivityLog extends Model
{
    /** The tabs of the Activity Logs page: category => label. */
    public const CATEGORIES = [
        'verification' => 'Verification',
        'tickets' => 'Tickets',
        'signatory_lists' => 'Signatory Lists',
        'organizations' => 'Organizations',
        'accounts' => 'Accounts',
    ];

    public const UPDATED_AT = null;   // lines are never updated

    protected $guarded = [];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Activity log lines cannot be changed.'));
        static::deleting(fn () => throw new LogicException('Activity log lines cannot be deleted.'));
    }

    /**
     * Add one line. $actor = who did it (null = the system, e.g. a developer command).
     * The name and role are copied, so the line stays readable if the account changes later.
     */
    public static function record(?User $actor, string $category, string $action, string $details): self
    {
        return static::create([
            'user_id' => $actor?->id,
            'actor_name' => $actor?->displayName() ?? 'System',
            'actor_role' => $actor?->role,
            'category' => $category,
            'action' => $action,
            'details' => mb_substr($details !== '' ? $details : '—', 0, 500),
        ]);
    }

    public function roleLabel(): string
    {
        return User::ROLE_LABELS[$this->actor_role] ?? 'System';
    }
}
