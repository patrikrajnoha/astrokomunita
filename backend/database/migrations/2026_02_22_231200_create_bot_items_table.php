<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_items', function (Blueprint $table) {
            $table->id();
            $table->string('bot_identity', 20);
            $table->foreignId('source_id')->constrained('bot_sources')->cascadeOnDelete();
            $table->string('stable_key', 191);
            $table->text('title');
            $table->text('summary')->nullable();
            $table->longText('content')->nullable();
            $table->string('url', 2048)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('fetched_at')->useCurrent();
            $table->string('lang_original', 12)->nullable();
            $table->string('lang_detected', 12)->nullable();
            $table->text('title_translated')->nullable();
            $table->longText('content_translated')->nullable();
            $table->string('translation_status', 20)->default('pending');
            $table->string('publish_status', 20)->default('pending');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['source_id', 'stable_key'], 'bot_items_source_stable_key_unique');
            $table->index(['bot_identity', 'publish_status'], 'bot_items_identity_publish_status_index');
            $table->index('translation_status', 'bot_items_translation_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_items');
    }
};
