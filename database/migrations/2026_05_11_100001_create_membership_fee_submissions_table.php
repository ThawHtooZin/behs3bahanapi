<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_fee_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('members')->onDelete('cascade');
            $table->unsignedSmallInteger('fee_year');
            $table->unsignedTinyInteger('fee_month');
            $table->string('slip_image')->nullable();
            $table->date('claimed_payment_date');
            $table->unsignedInteger('amount_mmk')->default(3000);
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->boolean('was_late')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index(['member_id', 'fee_year', 'fee_month']);
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_fee_submissions');
    }
};
