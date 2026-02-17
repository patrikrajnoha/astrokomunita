<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_featured_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('event_id');
            $table->index('position');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_featured_events');
    }
};

