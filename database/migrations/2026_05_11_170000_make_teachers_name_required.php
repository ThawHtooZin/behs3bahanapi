<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('teachers')
            ->where(function ($query) {
                $query->whereNull('name')->orWhere('name', '');
            })
            ->update(['name' => 'Unknown']);

        Schema::table('teachers', function (Blueprint $table) {
            $table->string('name')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            $table->string('name')->nullable()->change();
        });
    }
};
