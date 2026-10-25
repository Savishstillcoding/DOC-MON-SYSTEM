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
        // Link table: which signatories sign for which organization, and in
        // what order. One row per (organization, signatory) pair, because the
        // step and title belong to the pair, not to the person.
        Schema::create('organization_signatory', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('step');    // 1 = signs first; the highest step has the final say
            $table->string('title');                // e.g. "Adviser", "VPSD"
            $table->timestamps();

            $table->unique(['organization_id', 'step']);     // no two signatories on the same step
            $table->unique(['organization_id', 'user_id']);  // a person appears once per organization
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization_signatory');
    }
};
