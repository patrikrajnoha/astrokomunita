<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_candidate_publish_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('status', 32)->default('queued');
            $table->foreignId('reviewer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('publish_generation_mode', 16)->default('template');

            $table->unsignedInteger('total_selected')->default(0);
            $table->unsignedInteger('processed')->default(0);
            $table->unsignedInteger('published')->default(0);
            $table->unsignedInteger('failed')->default(0);
            $table->unsignedInteger('limit_applied')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();

            $table->json('filters')->nullable();
            $table->json('meta')->nullable();
            $table->text('error_message')->nullable();

            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['reviewer_user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_candidate_publish_runs');
    }
};
