<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bot_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('source_id')->nullable()->constrained('bot_sources')->nullOnDelete();
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('interval_minutes');
            $table->unsignedInteger('jitter_seconds')->default(0);
            $table->string('timezone', 64)->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->string('last_result', 20)->nullable();
            $table->string('last_message', 500)->nullable();
            $table->timestamps();

            $table->index(['enabled', 'next_run_at'], 'bot_schedules_enabled_next_run_index');
            $table->index(['bot_user_id', 'enabled'], 'bot_schedules_bot_enabled_index');
            $table->index('source_id', 'bot_schedules_source_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_schedules');
    }
};

