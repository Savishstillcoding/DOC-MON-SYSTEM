<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a fully rejected ticket's Ref No. be used again (AUD-079).
 *
 * The old rule: a Ref No. once per organization, forever. The new rule: once per
 * organization among LIVE tickets. Status isn't stored (it's worked out from the
 * papers), so the database gets its own copy to check: active_ref_no holds the Ref No.
 * while the ticket is live and is emptied (NULL) when every paper is rejected. A unique
 * rule allows any number of NULLs, so rejected tickets free their number, and two
 * officers submitting at the same second still can't both get it. ref_no itself is kept,
 * so the rejected ticket still shows its number.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('active_ref_no', 20)->nullable()->after('ref_no');
        });

        // Existing tickets: live ones keep their number, fully rejected ones release it
        DB::table('tickets')->update(['active_ref_no' => DB::raw('ref_no')]);
        DB::table('tickets')
            ->whereExists(fn ($q) => $q->from('documents')->whereColumn('documents.ticket_id', 'tickets.id'))
            ->whereNotExists(fn ($q) => $q->from('documents')->whereColumn('documents.ticket_id', 'tickets.id')
                ->where('status', '!=', 'rejected'))
            ->update(['active_ref_no' => null]);

        // New rule first, old rule second: on MySQL/TiDB the foreign key on organization_id
        // needs an index starting with that column at every moment
        Schema::table('tickets', function (Blueprint $table) {
            $table->unique(['organization_id', 'active_ref_no']);
        });
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropUnique(['organization_id', 'ref_no']);
        });
    }

    /** Fails if a Ref No. was already reused: the old rule can't hold two tickets with one number. */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->unique(['organization_id', 'ref_no']);
        });
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropUnique(['organization_id', 'active_ref_no']);
            $table->dropColumn('active_ref_no');
        });
    }
};
