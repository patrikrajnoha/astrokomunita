<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporter_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('target_type', 20)->default('post');
            $table->foreignId('target_id')->constrained('posts')->cascadeOnDelete();
            $table->string('reason', 30);
            $table->text('message')->nullable();
            $table->string('status', 20)->default('open');
            $table->string('admin_action', 20)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['reporter_user_id', 'target_type', 'target_id'], 'reports_unique_target');
            $table->index(['target_type', 'target_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
