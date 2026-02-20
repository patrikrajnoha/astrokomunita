<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('newsletter_runs', function (Blueprint $table): void {
            $table->id();
            $table->date('week_start_date');
            $table->enum('status', ['pending', 'running', 'completed', 'failed'])->default('pending');
            $table->unsignedInteger('total_recipients')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->foreignId('admin_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('forced')->default(false);
            $table->boolean('dry_run')->default(false);
            $table->text('error')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index('week_start_date');
            $table->index(['week_start_date', 'status'], 'newsletter_runs_week_status_index');
            $table->index(['status', 'created_at'], 'newsletter_runs_status_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_runs');
    }
};
