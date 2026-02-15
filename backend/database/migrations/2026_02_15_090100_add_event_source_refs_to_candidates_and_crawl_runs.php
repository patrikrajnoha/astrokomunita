<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_candidates', function (Blueprint $table) {
            $table->foreignId('event_source_id')
                ->nullable()
                ->after('id')
                ->constrained('event_sources')
                ->nullOnDelete();
            $table->string('external_id', 191)->nullable()->after('source_uid');
            $table->index(['event_source_id', 'created_at'], 'event_candidates_source_created_idx');
        });

        Schema::table('crawl_runs', function (Blueprint $table) {
            $table->foreignId('event_source_id')
                ->nullable()
                ->after('id')
                ->constrained('event_sources')
                ->nullOnDelete();
            $table->unsignedSmallInteger('year')->nullable()->after('source_url');
            $table->string('status', 20)->default('success')->after('finished_at');
            $table->unsignedInteger('fetched_count')->default(0)->after('fetched_bytes');
            $table->unsignedInteger('created_candidates_count')->default(0)->after('inserted_candidates');
            $table->unsignedInteger('skipped_duplicates_count')->default(0)->after('duplicates');
            $table->longText('error_summary')->nullable()->after('error_log');
            $table->index(['event_source_id', 'year', 'started_at'], 'crawl_runs_source_year_started_idx');
        });

        if (Schema::hasTable('event_sources')) {
            $sourceMap = DB::table('event_sources')->pluck('id', 'key');

            if (isset($sourceMap['astropixels'])) {
                DB::table('event_candidates')
                    ->where('source_name', 'astropixels')
                    ->update([
                        'event_source_id' => $sourceMap['astropixels'],
                        'external_id' => DB::raw('COALESCE(source_uid, external_id)'),
                    ]);

                DB::table('crawl_runs')
                    ->where('source_name', 'astropixels')
                    ->update(['event_source_id' => $sourceMap['astropixels']]);
            }

            if (isset($sourceMap['nasa'])) {
                DB::table('event_candidates')
                    ->where('source_name', 'nasa')
                    ->update([
                        'event_source_id' => $sourceMap['nasa'],
                        'external_id' => DB::raw('COALESCE(source_uid, external_id)'),
                    ]);

                DB::table('crawl_runs')
                    ->where('source_name', 'nasa')
                    ->update(['event_source_id' => $sourceMap['nasa']]);
            }
        }

        Schema::table('event_candidates', function (Blueprint $table) {
            $table->unique(['event_source_id', 'external_id'], 'event_candidates_source_external_unique');
            $table->index(['event_source_id', 'source_hash'], 'event_candidates_source_hash_idx');
        });
    }

    public function down(): void
    {
        Schema::table('event_candidates', function (Blueprint $table) {
            $table->dropUnique('event_candidates_source_external_unique');
            $table->dropIndex('event_candidates_source_hash_idx');
            $table->dropIndex('event_candidates_source_created_idx');
            $table->dropConstrainedForeignId('event_source_id');
            $table->dropColumn('external_id');
        });

        Schema::table('crawl_runs', function (Blueprint $table) {
            $table->dropIndex('crawl_runs_source_year_started_idx');
            $table->dropConstrainedForeignId('event_source_id');
            $table->dropColumn([
                'year',
                'status',
                'fetched_count',
                'created_candidates_count',
                'skipped_duplicates_count',
                'error_summary',
            ]);
        });
    }
};
