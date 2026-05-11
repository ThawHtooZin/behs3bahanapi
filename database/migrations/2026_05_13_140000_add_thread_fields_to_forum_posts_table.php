<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forum_posts', function (Blueprint $table) {
            if (!Schema::hasColumn('forum_posts', 'title')) {
                $table->string('title')->nullable()->after('category');
            }
            if (!Schema::hasColumn('forum_posts', 'description')) {
                $table->text('description')->nullable()->after('title');
            }
            if (!Schema::hasColumn('forum_posts', 'views_count')) {
                $table->unsignedInteger('views_count')->default(0)->after('description');
            }
        });

        if (Schema::hasColumn('forum_posts', 'content')) {
            DB::table('forum_posts')
                ->whereNull('title')
                ->update([
                    'title' => DB::raw("substr(content, 1, 80)"),
                    'description' => DB::raw('content'),
                ]);
        }

        DB::table('forum_posts')->whereNull('title')->update(['title' => 'Untitled']);
        DB::table('forum_posts')->whereNull('description')->update(['description' => '']);
    }

    public function down(): void
    {
        Schema::table('forum_posts', function (Blueprint $table) {
            foreach (['views_count', 'description', 'title'] as $column) {
                if (Schema::hasColumn('forum_posts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
