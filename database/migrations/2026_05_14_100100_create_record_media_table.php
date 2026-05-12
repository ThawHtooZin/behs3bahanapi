<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('record_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('record_id')->constrained('records')->cascadeOnDelete();
            $table->string('type', 16); // image | video
            $table->string('path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('original_name')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['record_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('record_media');
    }
};
