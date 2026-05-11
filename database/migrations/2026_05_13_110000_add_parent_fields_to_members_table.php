<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            if (!Schema::hasColumn('members', 'parent_name')) {
                $table->string('parent_name')->nullable()->after('email');
            }
            if (!Schema::hasColumn('members', 'parent_occupation')) {
                $table->string('parent_occupation')->nullable()->after('parent_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            if (Schema::hasColumn('members', 'parent_occupation')) {
                $table->dropColumn('parent_occupation');
            }
            if (Schema::hasColumn('members', 'parent_name')) {
                $table->dropColumn('parent_name');
            }
        });
    }
};
