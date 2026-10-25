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
        // History: one row every time someone acts on a document (approve,
        // return, reject, resubmit). Never edited or deleted, so it can always
        // prove who did what and when. Also feeds the "Recent Activity" lists.
        Schema::create('document_actions', function (Blueprint $table) {
            $table->id();

            // restrictOnDelete on both: history is permanent. As a side effect,
            // a ticket can only be deleted while nobody has acted on it yet.
            $table->foreignId('document_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete(); // who did it (signatory, or officer for resubmit)

            $table->unsignedTinyInteger('step');   // which step the document was at
            $table->string('action', 20);          // approved | returned | rejected | resubmitted
            $table->text('remarks')->nullable();   // e.g. why it was returned or rejected
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_actions');
    }
};
