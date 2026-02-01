<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manual_events', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->longText('description')->nullable();
            $table->string('event_type', 60);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->unsignedTinyInteger('visibility')->default(1);
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('status', 20)->default('draft');
            $table->foreignId('published_event_id')->nullable()->constrained('events')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_events');
    }
};
