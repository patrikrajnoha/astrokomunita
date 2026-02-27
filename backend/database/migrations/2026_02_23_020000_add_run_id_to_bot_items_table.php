<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bot_items', function (Blueprint $table) {
            $table->foreignId('run_id')
                ->nullable()
                ->after('source_id')
                ->constrained('bot_runs')
                ->nullOnDelete();

            $table->index(['source_id', 'run_id'], 'bot_items_source_run_id_index');
            $table->index('run_id', 'bot_items_run_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('bot_items', function (Blueprint $table) {
            $table->dropIndex('bot_items_source_run_id_index');
            $table->dropIndex('bot_items_run_id_index');
            $table->dropConstrainedForeignId('run_id');
        });
    }
};
