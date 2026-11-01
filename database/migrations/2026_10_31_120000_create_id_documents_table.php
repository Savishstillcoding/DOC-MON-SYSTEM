<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The ID photo (or PDF) uploaded at sign-up, stored INSIDE the database (AUD-012):
 * Render wipes the container's disk on every deploy, so files saved there would vanish.
 * A separate table (not a column on users) keeps loading a user fast: the 2 MB
 * file is only read when the admin opens it. Deleted once the admin decides.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('id_documents', function (Blueprint $table) {
            $table->id();

            // One ID per account; deleting the account deletes its ID too
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            $table->string('mime', 50);              // image/jpeg, image/png, image/webp or application/pdf
            $table->unsignedInteger('size');         // bytes, at most 2 MB
            $table->binary('data');                  // the file itself

            $table->timestamps();
        });

        // Laravel's binary() is a BLOB on MySQL/TiDB, which holds only 64 KB.
        // MEDIUMBLOB holds up to 16 MB, enough for a 2 MB photo. (SQLite has no limit.)
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE id_documents MODIFY data MEDIUMBLOB NOT NULL');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('id_documents');
    }
};
