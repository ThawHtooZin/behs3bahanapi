<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['news_items', 'announcements'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                if (Schema::hasColumn($blueprint->getTable(), 'sort_order')) {
                    $blueprint->dropColumn('sort_order');
                }
                if (Schema::hasColumn($blueprint->getTable(), 'published_at')) {
                    $blueprint->dropColumn('published_at');
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['news_items', 'announcements'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->unsignedInteger('sort_order')->default(0);
                $blueprint->timestamp('published_at')->nullable();
            });
        }
    }
};
