<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bot_items', function (Blueprint $table) {
            if (!Schema::hasColumn('bot_items', 'translation_error')) {
                $table->text('translation_error')->nullable()->after('translation_status');
            }

            if (!Schema::hasColumn('bot_items', 'translation_provider')) {
                $table->string('translation_provider', 40)->nullable()->after('translation_error');
                $table->index('translation_provider', 'bot_items_translation_provider_index');
            }

            if (!Schema::hasColumn('bot_items', 'translated_at')) {
                $table->timestamp('translated_at')->nullable()->after('translation_provider');
                $table->index('translated_at', 'bot_items_translated_at_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bot_items', function (Blueprint $table) {
            if (Schema::hasColumn('bot_items', 'translated_at')) {
                $table->dropIndex('bot_items_translated_at_index');
                $table->dropColumn('translated_at');
            }

            if (Schema::hasColumn('bot_items', 'translation_provider')) {
                $table->dropIndex('bot_items_translation_provider_index');
                $table->dropColumn('translation_provider');
            }

            if (Schema::hasColumn('bot_items', 'translation_error')) {
                $table->dropColumn('translation_error');
            }
        });
    }
};

