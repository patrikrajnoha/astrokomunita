<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            if (! Schema::hasColumn('posts', 'bot_item_id')) {
                $table->foreignId('bot_item_id')
                    ->nullable()
                    ->after('source_uid')
                    ->constrained('bot_items')
                    ->nullOnDelete();
                $table->unique('bot_item_id', 'posts_bot_item_id_unique');
            }
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            if (Schema::hasColumn('posts', 'bot_item_id')) {
                $table->dropUnique('posts_bot_item_id_unique');
                $table->dropConstrainedForeignId('bot_item_id');
            }
        });
    }
};

