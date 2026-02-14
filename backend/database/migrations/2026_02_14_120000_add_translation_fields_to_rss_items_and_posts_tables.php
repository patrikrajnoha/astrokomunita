<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rss_items', function (Blueprint $table) {
            if (! Schema::hasColumn('rss_items', 'original_title')) {
                $table->text('original_title')->nullable()->after('title');
            }

            if (! Schema::hasColumn('rss_items', 'original_summary')) {
                $table->text('original_summary')->nullable()->after('summary');
            }

            if (! Schema::hasColumn('rss_items', 'translated_title')) {
                $table->text('translated_title')->nullable()->after('original_summary');
            }

            if (! Schema::hasColumn('rss_items', 'translated_summary')) {
                $table->text('translated_summary')->nullable()->after('translated_title');
            }

            if (! Schema::hasColumn('rss_items', 'translation_status')) {
                $table->string('translation_status', 16)->default('pending')->after('translated_summary');
                $table->index('translation_status', 'rss_items_translation_status_index');
            }

            if (! Schema::hasColumn('rss_items', 'translation_error')) {
                $table->text('translation_error')->nullable()->after('translation_status');
            }

            if (! Schema::hasColumn('rss_items', 'translated_at')) {
                $table->dateTime('translated_at')->nullable()->after('translation_error');
            }
        });

        Schema::table('posts', function (Blueprint $table) {
            if (! Schema::hasColumn('posts', 'original_title')) {
                $table->text('original_title')->nullable()->after('content');
            }

            if (! Schema::hasColumn('posts', 'original_body')) {
                $table->longText('original_body')->nullable()->after('original_title');
            }

            if (! Schema::hasColumn('posts', 'translated_title')) {
                $table->text('translated_title')->nullable()->after('original_body');
            }

            if (! Schema::hasColumn('posts', 'translated_body')) {
                $table->longText('translated_body')->nullable()->after('translated_title');
            }

            if (! Schema::hasColumn('posts', 'translation_status')) {
                $table->string('translation_status', 16)->default('pending')->after('translated_body');
                $table->index('translation_status', 'posts_translation_status_index');
            }

            if (! Schema::hasColumn('posts', 'translation_error')) {
                $table->text('translation_error')->nullable()->after('translation_status');
            }

            if (! Schema::hasColumn('posts', 'translated_at')) {
                $table->dateTime('translated_at')->nullable()->after('translation_error');
            }
        });
    }

    public function down(): void
    {
        Schema::table('rss_items', function (Blueprint $table) {
            if (Schema::hasColumn('rss_items', 'translated_at')) {
                $table->dropColumn('translated_at');
            }

            if (Schema::hasColumn('rss_items', 'translation_error')) {
                $table->dropColumn('translation_error');
            }

            if (Schema::hasColumn('rss_items', 'translation_status')) {
                $table->dropIndex('rss_items_translation_status_index');
                $table->dropColumn('translation_status');
            }

            if (Schema::hasColumn('rss_items', 'translated_summary')) {
                $table->dropColumn('translated_summary');
            }

            if (Schema::hasColumn('rss_items', 'translated_title')) {
                $table->dropColumn('translated_title');
            }

            if (Schema::hasColumn('rss_items', 'original_summary')) {
                $table->dropColumn('original_summary');
            }

            if (Schema::hasColumn('rss_items', 'original_title')) {
                $table->dropColumn('original_title');
            }
        });

        Schema::table('posts', function (Blueprint $table) {
            if (Schema::hasColumn('posts', 'translated_at')) {
                $table->dropColumn('translated_at');
            }

            if (Schema::hasColumn('posts', 'translation_error')) {
                $table->dropColumn('translation_error');
            }

            if (Schema::hasColumn('posts', 'translation_status')) {
                $table->dropIndex('posts_translation_status_index');
                $table->dropColumn('translation_status');
            }

            if (Schema::hasColumn('posts', 'translated_body')) {
                $table->dropColumn('translated_body');
            }

            if (Schema::hasColumn('posts', 'translated_title')) {
                $table->dropColumn('translated_title');
            }

            if (Schema::hasColumn('posts', 'original_body')) {
                $table->dropColumn('original_body');
            }

            if (Schema::hasColumn('posts', 'original_title')) {
                $table->dropColumn('original_title');
            }
        });
    }
};
