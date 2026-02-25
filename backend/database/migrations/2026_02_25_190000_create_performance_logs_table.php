<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('key');
            $table->string('environment');
            $table->unsignedInteger('sample_size');
            $table->unsignedInteger('duration_ms');
            $table->decimal('avg_ms', 10, 2);
            $table->decimal('p95_ms', 10, 2);
            $table->unsignedInteger('min_ms');
            $table->unsignedInteger('max_ms');
            $table->decimal('db_queries_avg', 10, 2)->nullable();
            $table->decimal('db_queries_p95', 10, 2)->nullable();
            $table->json('payload')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['key', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_logs');
    }
};

