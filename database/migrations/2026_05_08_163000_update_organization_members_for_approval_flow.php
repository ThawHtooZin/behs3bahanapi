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
        Schema::table('organization_members', function (Blueprint $table) {
            if (!Schema::hasColumn('organization_members', 'dob')) {
                $table->date('dob')->nullable()->after('image');
            }

            if (Schema::hasColumn('organization_members', 'age')) {
                $table->dropColumn('age');
            }

            if (!Schema::hasColumn('organization_members', 'agreed_to_rules')) {
                $table->boolean('agreed_to_rules')->default(false)->after('email');
            }

            if (!Schema::hasColumn('organization_members', 'status')) {
                $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending')->after('agreed_to_rules');
            }

            if (!Schema::hasColumn('organization_members', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('status');
            }

            if (!Schema::hasColumn('organization_members', 'approved_by')) {
                $table->foreignId('approved_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organization_members', function (Blueprint $table) {
            if (Schema::hasColumn('organization_members', 'approved_by')) {
                $table->dropConstrainedForeignId('approved_by');
            }

            $dropColumns = [];
            foreach (['approved_at', 'status', 'agreed_to_rules'] as $column) {
                if (Schema::hasColumn('organization_members', $column)) {
                    $dropColumns[] = $column;
                }
            }

            if (!empty($dropColumns)) {
                $table->dropColumn($dropColumns);
            }

            if (!Schema::hasColumn('organization_members', 'age')) {
                $table->unsignedTinyInteger('age')->nullable()->after('image');
            }

            if (Schema::hasColumn('organization_members', 'dob')) {
                $table->dropColumn('dob');
            }
        });
    }
};
