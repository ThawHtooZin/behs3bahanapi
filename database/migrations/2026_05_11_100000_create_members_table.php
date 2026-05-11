<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('organization_member_id')->nullable()->constrained()->nullOnDelete();
            $table->string('member_no')->unique();
            $table->string('name');
            $table->string('contact_number');
            $table->text('address');
            $table->date('enrolled_at');
            $table->unsignedTinyInteger('fee_due_day');
            $table->timestamps();

            $table->unique('user_id');
            $table->unique('organization_member_id');
        });

        if (Schema::hasTable('organization_members')) {
            $rows = DB::table('organization_members')->where('status', 'approved')->get();
            foreach ($rows as $om) {
                if (DB::table('members')->where('user_id', $om->user_id)->exists()) {
                    continue;
                }
                $approvedAt = $om->approved_at
                    ? \Carbon\Carbon::parse($om->approved_at)
                    : now();
                $day = min(max((int) $approvedAt->day, 1), 31);

                DB::table('members')->insert([
                    'user_id' => $om->user_id,
                    'organization_member_id' => $om->id,
                    'member_no' => 'M-'.str_pad((string) $om->id, 5, '0', STR_PAD_LEFT),
                    'name' => $om->name,
                    'contact_number' => $om->contact_number,
                    'address' => $om->address,
                    'enrolled_at' => $approvedAt->toDateString(),
                    'fee_due_day' => $day,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
