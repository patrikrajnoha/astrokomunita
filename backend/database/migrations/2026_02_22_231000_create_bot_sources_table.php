<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_sources', function (Blueprint $table) {
            $table->id();
            $table->string('key', 120)->unique();
            $table->string('bot_identity', 20);
            $table->string('source_type', 20);
            $table->string('url', 2048);
            $table->boolean('is_enabled')->default(true);
            $table->json('schedule')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();

            $table->index(['bot_identity', 'is_enabled'], 'bot_sources_identity_enabled_index');
            $table->index('source_type', 'bot_sources_source_type_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_sources');
    }
};

