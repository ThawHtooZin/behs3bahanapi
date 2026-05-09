<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('organization_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('name');
            $table->string('nrc_number');
            $table->string('gender');
            $table->string('image')->nullable();
            $table->date('dob');
            $table->text('address');
            $table->string('contact_number');
            $table->string('email');
            $table->boolean('agreed_to_rules')->default(false);
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('user_id');
            $table->unique('nrc_number');
        });

        DB::table('roles')->updateOrInsert(
            ['slug' => 'member'],
            [
                'name' => 'Member',
                'description' => 'Organization member access',
                'has_dashboard_access' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization_members');
        DB::table('roles')->where('slug', 'member')->delete();
    }
};
