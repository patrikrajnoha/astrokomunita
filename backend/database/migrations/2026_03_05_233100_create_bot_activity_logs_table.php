<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->string('bot_identity', 20)->nullable();
            $table->foreignId('source_id')->nullable()->constrained('bot_sources')->nullOnDelete();
            $table->foreignId('run_id')->nullable()->constrained('bot_runs')->nullOnDelete();
            $table->foreignId('bot_item_id')->nullable()->constrained('bot_items')->nullOnDelete();
            $table->foreignId('post_id')->nullable()->constrained('posts')->nullOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 50);
            $table->string('outcome', 20);
            $table->string('reason', 120)->nullable();
            $table->string('run_context', 20)->nullable();
            $table->string('message', 500)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['bot_identity', 'created_at'], 'bot_activity_logs_identity_created_index');
            $table->index(['action', 'outcome', 'created_at'], 'bot_activity_logs_action_outcome_created_index');
            $table->index(['source_id', 'created_at'], 'bot_activity_logs_source_created_index');
            $table->index('reason', 'bot_activity_logs_reason_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_activity_logs');
    }
};

