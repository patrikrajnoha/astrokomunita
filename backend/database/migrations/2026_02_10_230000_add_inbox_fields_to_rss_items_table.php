<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rss_items', function (Blueprint $table) {
            if (! Schema::hasColumn('rss_items', 'stable_key')) {
                $table->string('stable_key', 64)->nullable()->after('dedupe_hash');
            }
            if (! Schema::hasColumn('rss_items', 'published_to_posts_at')) {
                $table->dateTime('published_to_posts_at')->nullable()->after('published_at');
            }
            if (! Schema::hasColumn('rss_items', 'reviewed_by')) {
                $table->foreignId('reviewed_by')->nullable()->after('post_id')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('rss_items', 'reviewed_at')) {
                $table->dateTime('reviewed_at')->nullable()->after('reviewed_by');
            }
            if (! Schema::hasColumn('rss_items', 'review_note')) {
                $table->text('review_note')->nullable()->after('reviewed_at');
            }
        });

        DB::table('rss_items')
            ->whereNull('stable_key')
            ->update(['stable_key' => DB::raw('dedupe_hash')]);

        Schema::table('rss_items', function (Blueprint $table) {
            $table->unique('stable_key', 'rss_items_stable_key_unique');
            $table->index(['status', 'published_at'], 'rss_items_status_published_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('rss_items', function (Blueprint $table) {
            $table->dropIndex('rss_items_status_published_at_index');
            $table->dropUnique('rss_items_stable_key_unique');
        });

        Schema::table('rss_items', function (Blueprint $table) {
            if (Schema::hasColumn('rss_items', 'reviewed_by')) {
                $table->dropConstrainedForeignId('reviewed_by');
            }
            if (Schema::hasColumn('rss_items', 'reviewed_at')) {
                $table->dropColumn('reviewed_at');
            }
            if (Schema::hasColumn('rss_items', 'review_note')) {
                $table->dropColumn('review_note');
            }
            if (Schema::hasColumn('rss_items', 'published_to_posts_at')) {
                $table->dropColumn('published_to_posts_at');
            }
            if (Schema::hasColumn('rss_items', 'stable_key')) {
                $table->dropColumn('stable_key');
            }
        });
    }
};

