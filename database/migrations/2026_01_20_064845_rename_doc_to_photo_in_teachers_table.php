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
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('ALTER TABLE teachers RENAME COLUMN photo TO doc');
        } else {
            Schema::table('teachers', function (Blueprint $table) {
                $table->renameColumn('photo', 'doc');
            });
        }
    }
};
