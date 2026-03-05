<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            if (!Schema::hasColumn('posts', 'ingested_at')) {
                $table->timestamp('ingested_at')
                    ->nullable()
                    ->after('source_published_at');
            }

            $table->index(['source_name', 'ingested_at'], 'posts_source_name_ingested_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            if (Schema::hasColumn('posts', 'ingested_at')) {
                $table->dropIndex('posts_source_name_ingested_at_index');
                $table->dropColumn('ingested_at');
            }
        });
    }
};

