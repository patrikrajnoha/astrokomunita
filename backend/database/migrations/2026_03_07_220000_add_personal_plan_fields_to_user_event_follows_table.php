<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user_event_follows')) {
            return;
        }

        Schema::table('user_event_follows', function (Blueprint $table): void {
            if (! Schema::hasColumn('user_event_follows', 'personal_note')) {
                $table->text('personal_note')->nullable()->after('event_id');
            }

            if (! Schema::hasColumn('user_event_follows', 'reminder_at')) {
                $table->dateTime('reminder_at')->nullable()->after('personal_note')->index();
            }

            if (! Schema::hasColumn('user_event_follows', 'planned_time')) {
                $table->dateTime('planned_time')->nullable()->after('reminder_at');
            }

            if (! Schema::hasColumn('user_event_follows', 'planned_location_label')) {
                $table->string('planned_location_label', 160)->nullable()->after('planned_time');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('user_event_follows')) {
            return;
        }

        Schema::table('user_event_follows', function (Blueprint $table): void {
            $columnsToDrop = [];

            foreach (['personal_note', 'reminder_at', 'planned_time', 'planned_location_label'] as $column) {
                if (Schema::hasColumn('user_event_follows', $column)) {
                    $columnsToDrop[] = $column;
                }
            }

            if ($columnsToDrop !== []) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
