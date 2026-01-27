<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crawl_runs', function (Blueprint $table) {
            $table->id();

            $table->string('source_name', 100);
            $table->text('source_url');

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();

            // metriky (pre BP super)
            $table->unsignedInteger('fetched_bytes')->default(0);
            $table->unsignedInteger('parsed_items')->default(0);
            $table->unsignedInteger('inserted_candidates')->default(0);
            $table->unsignedInteger('duplicates')->default(0);
            $table->unsignedInteger('errors_count')->default(0);

            // jednoduché logy chýb
            $table->longText('error_log')->nullable();

            $table->timestamps();

            $table->index(['source_name', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crawl_runs');
    }
};
