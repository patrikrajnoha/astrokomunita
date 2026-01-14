<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_candidates', function (Blueprint $table) {
            $table->id();

            // Zdroj + deduplikácia
            $table->string('source_name', 100);
            $table->text('source_url')->nullable();
            $table->string('source_uid', 191)->nullable();     // ID zo zdroja, ak existuje
            $table->string('source_hash', 64)->index();        // hash pre dedupe

            // Normalizované polia (čo admin vidí)
            $table->string('title', 255);
            $table->string('type', 50);                        // zatiaľ string, neskôr enum
            $table->dateTime('max_at');
            $table->dateTime('start_at')->nullable();
            $table->dateTime('end_at')->nullable();

            $table->text('short')->nullable();
            $table->longText('description')->nullable();
            $table->string('visibility', 50)->nullable();      // SK/EU/WORLD alebo text

            // Audit / reprodukovateľnosť
            $table->longText('raw_payload')->nullable();       // JSON ako text

            // Review flow
            $table->string('status', 20)->default('pending')->index(); // pending/approved/rejected/duplicate
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->text('reject_reason')->nullable();

            $table->timestamps();

            $table->index(['source_name', 'source_uid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_candidates');
    }
};
