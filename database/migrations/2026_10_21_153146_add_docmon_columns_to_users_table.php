<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Adds DOC-MON's account details to Laravel's default users table.
        // A NEW migration (not an edit of create_users_table) because that one
        // is already committed and has already run on TiDB.
        //
        // Columns added to an existing table must be nullable or have a default,
        // because rows that already exist need some value. Required fields like
        // first_name are enforced by the sign-up form's validation instead.
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('officer');    // officer | signatory | admin
            $table->string('status', 20)->default('pending');  // pending | verified | rejected (admin decides)
            $table->string('login_id', 30)->nullable()->unique(); // student ID, employee ID, or admin username

            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('phone', 11)->nullable();           // 09XXXXXXXXX, for SMS
            $table->string('position')->nullable();            // e.g. "Secretary", "Department Head"

            // Officers belong to one organization. Signatories link to theirs
            // through organization_signatory instead, so this stays empty for them.
            $table->foreignId('organization_id')->nullable()->constrained()->restrictOnDelete();

            // Signatories only: shown on officers' ticket cards
            $table->string('availability', 20)->nullable();    // Available | In a Meeting | In Class | Out of Office
            $table->string('back_at', 30)->nullable();         // free text, e.g. "2:00 PM", "Tomorrow"

            $table->string('id_document_path')->nullable();    // uploaded school/employee ID, for admin verification

            // Replaced by first_name + last_name (the sign-up form asks for them separately)
            $table->dropColumn('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Undo in reverse: remove the foreign key first, then the columns,
        // then bring back the old name column.
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organization_id');
            $table->dropUnique(['login_id']);
            $table->dropColumn([
                'role', 'status', 'login_id', 'first_name', 'last_name', 'phone',
                'position', 'availability', 'back_at', 'id_document_path',
            ]);
            $table->string('name')->nullable();
        });
    }
};
