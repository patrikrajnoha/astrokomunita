<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('translation_cache_entries', function (Blueprint $table) {
            $table->id();
            $table->string('cache_key', 64)->unique();
            $table->string('original_text_hash', 64);
            $table->string('language_from', 8)->default('en');
            $table->string('language_to', 8)->default('sk');
            $table->string('provider', 64)->nullable();
            $table->longText('translated_text');
            $table->unsignedInteger('hit_count')->default(0);
            $table->dateTime('last_used_at')->nullable();
            $table->timestamps();

            $table->index(['language_from', 'language_to'], 'translation_cache_lang_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('translation_cache_entries');
    }
};
