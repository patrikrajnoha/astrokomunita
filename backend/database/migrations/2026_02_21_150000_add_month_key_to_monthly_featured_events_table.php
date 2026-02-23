<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monthly_featured_events', function (Blueprint $table): void {
            $table->string('month_key', 7)->nullable()->after('event_id');
        });

        $defaultMonth = now((string) config('app.timezone', 'UTC'))->format('Y-m');

        DB::table('monthly_featured_events')
            ->whereNull('month_key')
            ->update(['month_key' => $defaultMonth]);

        Schema::table('monthly_featured_events', function (Blueprint $table): void {
            $table->dropForeign(['event_id']);
            $table->dropUnique('monthly_featured_events_event_id_unique');
            $table->index('event_id', 'monthly_featured_events_event_id_index');
            $table->unique(['month_key', 'event_id'], 'monthly_featured_events_month_event_unique');
            $table->index(['month_key', 'is_active', 'position'], 'monthly_featured_events_month_active_position_index');
            $table->foreign('event_id')->references('id')->on('events')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('monthly_featured_events', function (Blueprint $table): void {
            $table->dropForeign(['event_id']);
            $table->dropIndex('monthly_featured_events_month_active_position_index');
            $table->dropUnique('monthly_featured_events_month_event_unique');
            $table->dropIndex('monthly_featured_events_event_id_index');
            $table->unique('event_id');
            $table->foreign('event_id')->references('id')->on('events')->cascadeOnDelete();
            $table->dropColumn('month_key');
        });
    }
};
