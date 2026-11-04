<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Live updates (AUD-050): a short fingerprint of everything an officer's pages show
 * that OTHER people can change: the approval route, each signatory's availability,
 * and where each of the organization's papers is (shared tickets, AUD-075).
 *
 * The page carries the fingerprint it was drawn with; docmon-live.js asks for the
 * current one every 20 s and only re-downloads the page when the two differ.
 * Reads exact values (not only updated_at), so two changes within one second still count.
 */
class LiveStamp
{
    /** The fingerprint for this user's pages, or null when their role has no live pages. */
    public static function for(?User $user): ?string
    {
        return $user?->role === 'officer' ? self::officer($user) : null;
    }

    public static function officer(User $officer): string
    {
        $route = DB::table('organization_signatory as r')
            ->join('users as u', 'u.id', '=', 'r.user_id')
            ->where('r.organization_id', $officer->organization_id)
            ->orderBy('r.step')
            ->get(['r.step', 'r.title', 'u.id', 'u.first_name', 'u.last_name', 'u.availability', 'u.back_at']);

        $papers = DB::table('documents as d')
            ->join('tickets as t', 't.id', '=', 'd.ticket_id')
            ->where('t.organization_id', $officer->organization_id)
            ->orderBy('d.id')
            ->get(['d.id', 'd.status', 'd.current_step', 'd.updated_at']);

        return md5(json_encode([$route, $papers]));
    }
}
