<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('members')) {
            return;
        }

        Schema::table('members', function (Blueprint $table) {
            if (!Schema::hasColumn('members', 'nrc_number')) {
                $table->string('nrc_number')->nullable()->after('name');
            }
            if (!Schema::hasColumn('members', 'gender')) {
                $table->string('gender')->nullable()->after('nrc_number');
            }
            if (!Schema::hasColumn('members', 'image')) {
                $table->string('image')->nullable()->after('gender');
            }
            if (!Schema::hasColumn('members', 'dob')) {
                $table->date('dob')->nullable()->after('image');
            }
            if (!Schema::hasColumn('members', 'address')) {
                $table->text('address')->nullable()->after('dob');
            }
            if (!Schema::hasColumn('members', 'contact_number')) {
                $table->string('contact_number')->nullable()->after('address');
            }
            if (!Schema::hasColumn('members', 'email')) {
                $table->string('email')->nullable()->after('contact_number');
            }
            if (!Schema::hasColumn('members', 'agreed_to_rules')) {
                $table->boolean('agreed_to_rules')->default(false)->after('email');
            }
            if (!Schema::hasColumn('members', 'parent_name')) {
                $table->string('parent_name')->nullable()->after('email');
            }
            if (!Schema::hasColumn('members', 'parent_occupation')) {
                $table->string('parent_occupation')->nullable()->after('parent_name');
            }
            if (!Schema::hasColumn('members', 'status')) {
                $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending')->after('parent_occupation');
            }
            if (!Schema::hasColumn('members', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('status');
            }
            if (!Schema::hasColumn('members', 'approved_by')) {
                $table->foreignId('approved_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            }
        });

        if (Schema::hasTable('organization_members')) {
            $rows = DB::table('organization_members')->get();
            $validUserIds = DB::table('users')->pluck('id')->all();
            $validUserSet = array_fill_keys($validUserIds, true);
            foreach ($rows as $row) {
                if (!isset($validUserSet[$row->user_id])) {
                    continue;
                }
                $approvedBy = isset($row->approved_by) && isset($validUserSet[$row->approved_by])
                    ? $row->approved_by
                    : null;

                DB::table('members')->updateOrInsert(
                    ['user_id' => $row->user_id],
                    [
                        'name' => $row->name,
                        'nrc_number' => $row->nrc_number,
                        'gender' => $row->gender,
                        'image' => $row->image,
                        'dob' => $row->dob,
                        'address' => $row->address,
                        'contact_number' => $row->contact_number,
                        'email' => $row->email,
                        'agreed_to_rules' => $row->agreed_to_rules,
                        'status' => $row->status,
                        'approved_at' => $row->approved_at,
                        'approved_by' => $approvedBy,
                        'updated_at' => now(),
                        'created_at' => $row->created_at ?? now(),
                    ]
                );
            }
        }

        DB::statement("UPDATE members SET nrc_number = CONCAT('MIG-', id) WHERE nrc_number IS NULL OR nrc_number = ''");
        DB::statement("UPDATE members SET gender = 'မသတ်မှတ်' WHERE gender IS NULL OR gender = ''");
        DB::statement("UPDATE members SET dob = DATE(created_at) WHERE dob IS NULL");
        DB::statement("UPDATE members SET address = '-' WHERE address IS NULL OR address = ''");
        DB::statement("UPDATE members SET contact_number = '-' WHERE contact_number IS NULL OR contact_number = ''");
        DB::statement("UPDATE members SET email = COALESCE(email, '-') WHERE email IS NULL OR email = ''");
    }

    public function down(): void
    {
        //
    }
};
