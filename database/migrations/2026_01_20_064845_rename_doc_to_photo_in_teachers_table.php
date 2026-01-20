<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // On fresh installs the column is already named "photo", so only run if "doc" exists.
        if (! Schema::hasColumn('teachers', 'doc')) {
            return;
        }

        // SQLite doesn't support renameColumn, so we use raw SQL
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('ALTER TABLE teachers RENAME COLUMN doc TO photo');
        } else {
            Schema::table('teachers', function (Blueprint $table) {
                $table->renameColumn('doc', 'photo');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Only reverse if the "photo" column exists.
        if (! Schema::hasColumn('teachers', 'photo')) {
            return;
        }

        if (DB::getDriverName() === 'sqlite') {
            DB::statement('ALTER TABLE teachers RENAME COLUMN photo TO doc');
        } else {
            Schema::table('teachers', function (Blueprint $table) {
                $table->renameColumn('photo', 'doc');
            });
        }
    }
};
