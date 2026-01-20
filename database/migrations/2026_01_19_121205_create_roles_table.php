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
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('has_dashboard_access')->default(false);
            $table->timestamps();
        });

        // Insert default roles
        DB::table('roles')->insert([
            ['id' => 1, 'name' => 'Admin', 'slug' => 'admin', 'description' => 'Full system access', 'has_dashboard_access' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'User', 'slug' => 'user', 'description' => 'Standard user access', 'has_dashboard_access' => false, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
