<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('translation_overrides', function (Blueprint $table) {
            $table->id();
            $table->string('source_term', 255);
            $table->string('target_term', 255);
            $table->string('language_from', 8)->default('en');
            $table->string('language_to', 8)->default('sk');
            $table->boolean('is_case_sensitive')->default(false);
            $table->timestamps();

            $table->index(['language_from', 'language_to'], 'translation_overrides_lang_idx');
            $table->unique(
                ['source_term', 'language_from', 'language_to', 'is_case_sensitive'],
                'translation_overrides_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('translation_overrides');
    }
};
