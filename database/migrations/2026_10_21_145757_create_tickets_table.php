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
        // One ticket = one request filed by an officer. Its documents (one = Single,
        // more = Batch) live in the documents table, so there is no "type" column here.
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();     // e.g. "DM-2026-00123", made by DOC-MON
            $table->string('ref_no', 20);             // required: resolution no. typed by the Secretary/Deputy, e.g. "2026-09-008"

            // restrictOnDelete: an organization or officer that still has tickets
            // can't be deleted, so the approval history is never lost.
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('officer_id')->constrained('users')->restrictOnDelete();

            $table->string('title');
            $table->text('purpose')->nullable();      // notes for signatories (optional)
            $table->date('date_needed')->nullable();  // optional deadline
            $table->timestamps();

            // Each organization numbers its own resolutions, so the same Ref No.
            // can exist in two orgs but never twice in one.
            $table->unique(['organization_id', 'ref_no']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
