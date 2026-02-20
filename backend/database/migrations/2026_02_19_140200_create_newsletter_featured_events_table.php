<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('newsletter_featured_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('newsletter_run_id')->nullable()->constrained('newsletter_runs')->nullOnDelete();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->unsignedTinyInteger('order')->default(0);
            $table->timestamps();

            $table->index(['newsletter_run_id', 'order'], 'newsletter_featured_run_order_index');
            $table->index(['newsletter_run_id', 'event_id'], 'newsletter_featured_run_event_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_featured_events');
    }
};
