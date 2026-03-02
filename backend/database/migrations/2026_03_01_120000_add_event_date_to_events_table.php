<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dateTime('event_date')->nullable();
            $table->index('event_date', 'events_event_date_idx');
        });

        DB::table('events')->update([
            'event_date' => DB::raw('COALESCE(start_at, max_at)'),
        ]);
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dropIndex('events_event_date_idx');
            $table->dropColumn('event_date');
        });
    }
};
