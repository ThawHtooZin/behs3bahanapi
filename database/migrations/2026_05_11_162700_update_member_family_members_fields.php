<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_family_members', function (Blueprint $table) {
            if (!Schema::hasColumn('member_family_members', 'dob')) {
                $table->date('dob')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('member_family_members', function (Blueprint $table) {
            if (Schema::hasColumn('member_family_members', 'dob')) {
                $table->dropColumn('dob');
            }
        });
    }
};
