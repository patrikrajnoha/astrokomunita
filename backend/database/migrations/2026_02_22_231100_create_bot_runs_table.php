<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_runs', function (Blueprint $table) {
            $table->id();
            $table->string('bot_identity', 20);
            $table->foreignId('source_id')->nullable()->constrained('bot_sources')->nullOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->string('status', 20)->nullable();
            $table->json('stats')->nullable();
            $table->text('error_text')->nullable();
            $table->timestamps();

            $table->index(['bot_identity', 'status'], 'bot_runs_identity_status_index');
            $table->index(['source_id', 'started_at'], 'bot_runs_source_started_at_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_runs');
    }
};

