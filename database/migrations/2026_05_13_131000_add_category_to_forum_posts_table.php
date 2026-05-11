<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forum_posts', function (Blueprint $table) {
            if (!Schema::hasColumn('forum_posts', 'category')) {
                $table->string('category')->default('လူမှုရေး')->after('user_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('forum_posts', function (Blueprint $table) {
            if (Schema::hasColumn('forum_posts', 'category')) {
                $table->dropColumn('category');
            }
        });
    }
};
