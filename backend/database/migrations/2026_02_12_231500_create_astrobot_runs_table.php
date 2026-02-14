<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('astrobot_runs', function (Blueprint $table) {
            $table->id();
            $table->string('source', 32)->default('nasa');
            $table->string('trigger', 32)->default('scheduler');
            $table->string('status', 32)->default('success');
            $table->dateTime('started_at');
            $table->dateTime('finished_at')->nullable();
            $table->unsignedInteger('duration_ms')->default(0);
            $table->unsignedInteger('new_items')->default(0);
            $table->unsignedInteger('published_items')->default(0);
            $table->unsignedInteger('deleted_items')->default(0);
            $table->unsignedInteger('errors')->default(0);
            $table->text('error_message')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['source', 'started_at'], 'astrobot_runs_source_started_at_index');
            $table->index(['source', 'status'], 'astrobot_runs_source_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('astrobot_runs');
    }
};
