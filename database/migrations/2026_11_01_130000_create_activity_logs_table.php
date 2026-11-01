<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Activity Logs: a write-only diary of everything that happens in DOC-MON.
 * Rows are only ever added (the ActivityLog model refuses updates and deletes).
 * The person's name and role are copied into each row, so the log still reads
 * correctly after an account is edited or removed.
 *
 * up() also copies in the events that happened before this table existed
 * (decided 2026-11-01): sign-ups, verification decisions, tickets and document actions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            // Who did it; null = the system (e.g. a command run by a developer)
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('actor_name');
            $table->string('actor_role', 20)->nullable();
            $table->string('category', 20);    // verification | tickets | signatory_lists | organizations | accounts
            $table->string('action', 60);      // e.g. "Approved account", "Returned document"
            $table->string('details', 500);
            $table->timestamp('created_at')->index();

            $table->index(['category', 'created_at']);
        });

        $this->copyEarlierEvents();
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }

    /** One-time copy of what the database already knows, oldest first. */
    private function copyEarlierEvents(): void
    {
        $users = DB::table('users')->get()->keyBy('id');
        $orgs = DB::table('organizations')->pluck('code', 'id');
        $name = fn ($u) => $u ? trim("{$u->last_name}, {$u->first_name}", ', ') : 'Unknown';
        $roleLabel = ['officer' => 'Student Officer', 'signatory' => 'Signatory', 'admin' => 'Administrator'];
        $rows = [];

        foreach ($users as $u) {
            if (in_array($u->role, ['officer', 'signatory'], true)) {
                $rows[] = [$u, 'verification', 'Signed up',
                    collect([$orgs[$u->organization_id] ?? null, $u->position])->filter()->implode(' · '), $u->created_at];
            }
            if ($u->reviewed_at) {
                $rows[] = [$users[$u->reviewed_by] ?? null, 'verification',
                    $u->status === 'rejected' ? 'Rejected account' : 'Approved account',
                    $name($u).' ('.($roleLabel[$u->role] ?? $u->role).')'.($u->review_note ? " — {$u->review_note}" : ''),
                    $u->reviewed_at];
            }
        }

        $tickets = DB::table('tickets')->get()->keyBy('id');
        foreach ($tickets as $t) {
            $rows[] = [$users[$t->officer_id] ?? null, 'tickets', 'Submitted ticket', "{$t->code} · {$t->title}", $t->created_at];
        }

        $documents = DB::table('documents')->get()->keyBy('id');
        $verbs = ['approved' => 'Approved document', 'returned' => 'Returned document', 'rejected' => 'Rejected document', 'resubmitted' => 'Resubmitted document'];
        foreach (DB::table('document_actions')->orderBy('id')->get() as $a) {
            $doc = $documents[$a->document_id];
            $rows[] = [$users[$a->user_id] ?? null, 'tickets', $verbs[$a->action] ?? ucfirst($a->action),
                "{$tickets[$doc->ticket_id]->code} · {$doc->title}".($a->remarks ? " · {$a->remarks}" : ''), $a->created_at];
        }

        usort($rows, fn ($x, $y) => strcmp((string) $x[4], (string) $y[4]));

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('activity_logs')->insert(array_map(fn ($r) => [
                'user_id' => $r[0]?->id,
                'actor_name' => $r[0] ? $name($r[0]) : 'System',
                'actor_role' => $r[0]?->role,
                'category' => $r[1],
                'action' => $r[2],
                'details' => mb_substr($r[3] ?: '—', 0, 500),
                'created_at' => $r[4],
            ], $chunk));
        }
    }
};
