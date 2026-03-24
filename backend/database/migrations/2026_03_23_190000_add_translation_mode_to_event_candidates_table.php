<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_candidates', function (Blueprint $table): void {
            if (! Schema::hasColumn('event_candidates', 'translation_mode')) {
                $table->string('translation_mode', 24)
                    ->nullable()
                    ->after('translation_status');
                $table->index('translation_mode', 'event_candidates_translation_mode_idx');
            }
        });

        if (! Schema::hasColumn('event_candidates', 'translation_mode')) {
            return;
        }

        // Backfill existing rows using deterministic template signatures first.
        DB::table('event_candidates')
            ->where('translation_status', 'done')
            ->where(function ($query): void {
                $query->whereRaw("LOWER(COALESCE(translated_description, '')) LIKE '%maximum pribl%'")
                    ->orWhereRaw("LOWER(COALESCE(translated_description, '')) LIKE '%nastane pribl%'");
            })
            ->update(['translation_mode' => 'template']);

        // Remaining completed translations are machine translated unless manually patched later.
        DB::table('event_candidates')
            ->where('translation_status', 'done')
            ->whereNull('translation_mode')
            ->whereRaw("TRIM(COALESCE(translated_description, '')) <> ''")
            ->update(['translation_mode' => 'translated']);
    }

    public function down(): void
    {
        Schema::table('event_candidates', function (Blueprint $table): void {
            if (Schema::hasColumn('event_candidates', 'translation_mode')) {
                $table->dropIndex('event_candidates_translation_mode_idx');
                $table->dropColumn('translation_mode');
            }
        });
    }
};
