<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('record_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('record_id')->constrained('records')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 16); // like|love|haha|wow|sad|angry
            $table->timestamps();

            $table->unique(['record_id', 'user_id']);
            $table->index(['record_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('record_reactions');
    }
};
