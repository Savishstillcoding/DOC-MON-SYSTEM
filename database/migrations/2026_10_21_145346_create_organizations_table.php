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
        // Student organizations (DIGITS for now). Each one has its own
        // ordered list of signatories in organization_signatory.
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();   // short name shown everywhere, e.g. "DIGITS"
            $table->string('name');                 // full name of the organization
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
