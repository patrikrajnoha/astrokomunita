<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_candidates', function (Blueprint $table) {
            if (! Schema::hasColumn('event_candidates', 'original_title')) {
                $table->text('original_title')->nullable()->after('title');
            }

            if (! Schema::hasColumn('event_candidates', 'translated_title')) {
                $table->text('translated_title')->nullable()->after('original_title');
            }

            if (! Schema::hasColumn('event_candidates', 'original_description')) {
                $table->longText('original_description')->nullable()->after('description');
            }

            if (! Schema::hasColumn('event_candidates', 'translated_description')) {
                $table->longText('translated_description')->nullable()->after('original_description');
            }

            if (! Schema::hasColumn('event_candidates', 'translation_status')) {
                $table->string('translation_status', 16)->default('pending')->after('translated_description');
                $table->index('translation_status', 'event_candidates_translation_status_idx');
            }

            if (! Schema::hasColumn('event_candidates', 'translation_error')) {
                $table->string('translation_error', 120)->nullable()->after('translation_status');
            }

            if (! Schema::hasColumn('event_candidates', 'translated_at')) {
                $table->dateTime('translated_at')->nullable()->after('translation_error');
            }
        });
    }

    public function down(): void
    {
        Schema::table('event_candidates', function (Blueprint $table) {
            if (Schema::hasColumn('event_candidates', 'translated_at')) {
                $table->dropColumn('translated_at');
            }

            if (Schema::hasColumn('event_candidates', 'translation_error')) {
                $table->dropColumn('translation_error');
            }

            if (Schema::hasColumn('event_candidates', 'translation_status')) {
                $table->dropIndex('event_candidates_translation_status_idx');
                $table->dropColumn('translation_status');
            }

            if (Schema::hasColumn('event_candidates', 'translated_description')) {
                $table->dropColumn('translated_description');
            }

            if (Schema::hasColumn('event_candidates', 'original_description')) {
                $table->dropColumn('original_description');
            }

            if (Schema::hasColumn('event_candidates', 'translated_title')) {
                $table->dropColumn('translated_title');
            }

            if (Schema::hasColumn('event_candidates', 'original_title')) {
                $table->dropColumn('original_title');
            }
        });
    }
};
