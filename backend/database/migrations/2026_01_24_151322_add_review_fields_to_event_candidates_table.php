<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
public function up(): void
{
    Schema::table('event_candidates', function (Blueprint $table) {
        $table->foreignId('published_event_id')
            ->nullable()
            ->after('reviewed_at')
            ->constrained('events')
            ->nullOnDelete();
    });
}


public function down(): void
{
    Schema::table('event_candidates', function (Blueprint $table) {
        $table->dropConstrainedForeignId('published_event_id');
    });
}


};
