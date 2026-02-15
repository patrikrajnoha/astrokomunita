<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crawl_runs', function (Blueprint $table) {
            $table->unsignedSmallInteger('source_year')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->boolean('headers_used')->default(false);
            $table->unsignedInteger('updated_candidates_count')->default(0);
            $table->longText('diagnostics')->nullable();
            $table->string('error_code', 100)->nullable();

            $table->index(['event_source_id', 'source_year', 'status'], 'crawl_runs_source_year_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('crawl_runs', function (Blueprint $table) {
            $table->dropIndex('crawl_runs_source_year_status_idx');
            $table->dropColumn([
                'source_year',
                'duration_ms',
                'headers_used',
                'updated_candidates_count',
                'diagnostics',
                'error_code',
            ]);
        });
    }
};
