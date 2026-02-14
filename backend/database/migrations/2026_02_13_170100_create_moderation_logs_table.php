<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('moderation_logs', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type', 20);
            $table->unsignedBigInteger('entity_id');
            $table->string('decision', 20);
            $table->json('scores')->nullable();
            $table->json('labels')->nullable();
            $table->json('model_versions')->nullable();
            $table->integer('latency_ms')->default(0);
            $table->string('error_code', 120)->nullable();
            $table->string('request_hash', 64)->nullable();
            $table->string('request_excerpt', 255)->nullable();
            $table->foreignId('reviewed_by_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('admin_action', 20)->nullable();
            $table->text('admin_note')->nullable();
            $table->timestamps();

            $table->index(['entity_type', 'entity_id']);
            $table->index(['decision', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('moderation_logs');
    }
};
