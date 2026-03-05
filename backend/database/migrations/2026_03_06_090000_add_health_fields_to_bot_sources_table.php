<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bot_sources', function (Blueprint $table) {
            if (!Schema::hasColumn('bot_sources', 'name')) {
                $table->string('name', 160)->nullable()->after('key');
            }
            if (!Schema::hasColumn('bot_sources', 'last_success_at')) {
                $table->timestamp('last_success_at')->nullable()->after('last_run_at');
            }
            if (!Schema::hasColumn('bot_sources', 'last_error_at')) {
                $table->timestamp('last_error_at')->nullable()->after('last_success_at');
            }
            if (!Schema::hasColumn('bot_sources', 'last_error_message')) {
                $table->string('last_error_message', 500)->nullable()->after('last_error_at');
            }
            if (!Schema::hasColumn('bot_sources', 'consecutive_failures')) {
                $table->unsignedInteger('consecutive_failures')->default(0)->after('last_error_message');
            }
            if (!Schema::hasColumn('bot_sources', 'last_status_code')) {
                $table->unsignedSmallInteger('last_status_code')->nullable()->after('consecutive_failures');
            }
            if (!Schema::hasColumn('bot_sources', 'avg_latency_ms')) {
                $table->unsignedInteger('avg_latency_ms')->nullable()->after('last_status_code');
            }

            $table->index(['is_enabled', 'consecutive_failures'], 'bot_sources_enabled_failures_index');
            $table->index(['last_success_at', 'last_error_at'], 'bot_sources_success_error_index');
        });
    }

    public function down(): void
    {
        Schema::table('bot_sources', function (Blueprint $table) {
            if (Schema::hasColumn('bot_sources', 'avg_latency_ms')) {
                $table->dropIndex('bot_sources_success_error_index');
                $table->dropIndex('bot_sources_enabled_failures_index');
            }

            $columns = [
                'name',
                'last_success_at',
                'last_error_at',
                'last_error_message',
                'consecutive_failures',
                'last_status_code',
                'avg_latency_ms',
            ];

            $existing = array_values(array_filter($columns, static fn (string $column): bool => Schema::hasColumn('bot_sources', $column)));
            if ($existing !== []) {
                $table->dropColumn($existing);
            }
        });
    }
};

