<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('description_generation_runs', function (Blueprint $table): void {
            $table->id();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->string('status', 32)->default('running');

            $table->string('requested_mode', 32)->default('template');
            $table->string('effective_mode', 32)->default('template');
            $table->string('fallback_mode', 16)->default('skip');

            $table->boolean('resume_enabled')->default(false);
            $table->boolean('force_enabled')->default(false);
            $table->boolean('dry_run')->default(false);

            $table->unsignedBigInteger('from_id')->nullable();
            $table->unsignedInteger('limit')->nullable();
            $table->unsignedBigInteger('last_event_id')->nullable();

            $table->unsignedInteger('processed')->default(0);
            $table->unsignedInteger('generated')->default(0);
            $table->unsignedInteger('failed')->default(0);
            $table->unsignedInteger('skipped')->default(0);

            $table->json('meta')->nullable();
            $table->text('error_message')->nullable();

            $table->timestamps();

            $table->index(['status', 'requested_mode']);
            $table->index(['last_event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('description_generation_runs');
    }
};

