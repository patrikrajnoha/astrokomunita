<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_description_origins', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->string('source', 64);
            $table->string('source_detail', 120)->nullable();
            $table->foreignId('run_id')->nullable()->constrained('description_generation_runs')->nullOnDelete();
            $table->foreignId('candidate_id')->nullable()->constrained('event_candidates')->nullOnDelete();
            $table->char('description_hash', 64)->nullable();
            $table->char('short_hash', 64)->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['event_id', 'id'], 'event_description_origins_event_id_idx');
            $table->index(['source', 'id'], 'event_description_origins_source_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_description_origins');
    }
};

