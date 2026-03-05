<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('observations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->nullable()->constrained('events')->nullOnDelete();
            $table->foreignId('feed_post_id')->nullable()->constrained('posts')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->dateTime('observed_at');
            $table->decimal('location_lat', 10, 7)->nullable();
            $table->decimal('location_lng', 10, 7)->nullable();
            $table->string('location_name')->nullable();
            $table->unsignedTinyInteger('visibility_rating')->nullable();
            $table->text('equipment')->nullable();
            $table->boolean('is_public')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'observed_at']);
            $table->index('event_id');
            $table->index('is_public');
            $table->index('feed_post_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('observations');
    }
};
