<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('translation_logs', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 64);
            $table->string('status', 32);
            $table->string('error_code', 120)->nullable();
            $table->unsignedInteger('duration_ms')->default(0);
            $table->string('language_from', 8)->default('en');
            $table->string('language_to', 8)->default('sk');
            $table->string('original_text_hash', 64)->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at'], 'translation_logs_status_created_idx');
            $table->index(['provider', 'created_at'], 'translation_logs_provider_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('translation_logs');
    }
};
