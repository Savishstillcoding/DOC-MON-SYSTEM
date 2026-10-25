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
        // One physical paper inside a ticket. Each document moves through the
        // signatories on its own, so a Batch can have one document returned
        // while the others keep going.
        Schema::create('documents', function (Blueprint $table) {
            $table->id();

            // A document can't exist without its ticket, so deleting the ticket deletes it too.
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();

            $table->string('type');   // e.g. "Activity Proposal", "Budget Request"
            $table->string('title');  // e.g. "DIGITS Week Activity Proposal"

            // The paper's own number, if it has one (e.g. an attached activity
            // proposal). Optional: some papers only go under the ticket's Ref No.
            $table->string('ref_no', 20)->nullable();

            // Where this paper is right now:
            //   status       = pending | needs_revision | approved | rejected
            //   current_step = which signatory has it (1 = Adviser ... 4 = VPSD).
            // After a revision the step stays the same, so it goes straight back
            // to the signatory who returned it.
            $table->string('status', 20)->default('pending');
            $table->unsignedTinyInteger('current_step')->default(1);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
