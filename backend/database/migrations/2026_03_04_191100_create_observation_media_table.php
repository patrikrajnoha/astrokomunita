<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('observation_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('observation_id')->constrained('observations')->cascadeOnDelete();
            $table->string('path');
            $table->string('mime_type', 100)->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('observation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('observation_media');
    }
};
