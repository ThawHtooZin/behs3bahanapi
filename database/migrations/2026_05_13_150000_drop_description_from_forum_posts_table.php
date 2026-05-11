<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('forum_posts') || ! Schema::hasColumn('forum_posts', 'description')) {
            return;
        }

        DB::table('forum_posts')->orderBy('id')->chunkById(100, function ($rows): void {
            foreach ($rows as $row) {
                $content = (string) ($row->content ?? '');
                $description = (string) ($row->description ?? '');
                if (trim($content) === '' && trim($description) !== '') {
                    DB::table('forum_posts')->where('id', $row->id)->update(['content' => $description]);
                }
            }
        });

        Schema::table('forum_posts', function (Blueprint $table): void {
            $table->dropColumn('description');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('forum_posts') || Schema::hasColumn('forum_posts', 'description')) {
            return;
        }

        Schema::table('forum_posts', function (Blueprint $table): void {
            $table->text('description')->nullable()->after('title');
        });

        if (Schema::hasColumn('forum_posts', 'content')) {
            DB::statement('UPDATE forum_posts SET description = content');
        }
    }
};
