<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sidebar_section_configs', function (Blueprint $table) {
            $table->id();
            $table->string('scope', 64);
            $table->string('section_key', 128);
            $table->unsignedInteger('order')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->unique(['scope', 'section_key']);
            $table->index(['scope', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sidebar_section_configs');
    }
};
