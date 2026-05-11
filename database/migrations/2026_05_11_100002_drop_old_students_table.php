<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('old_students');
    }

    public function down(): void
    {
        // Intentionally empty: old_students removed from product.
    }
};
