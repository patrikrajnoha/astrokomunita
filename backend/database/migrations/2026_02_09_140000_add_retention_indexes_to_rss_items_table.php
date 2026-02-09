<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rss_items', function (Blueprint $table) {
            $table->index('created_at', 'rss_items_created_at_index');
            $table->index(['status', 'created_at'], 'rss_items_status_created_at_index');
            $table->index(['source', 'created_at'], 'rss_items_source_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('rss_items', function (Blueprint $table) {
            $table->dropIndex('rss_items_created_at_index');
            $table->dropIndex('rss_items_status_created_at_index');
            $table->dropIndex('rss_items_source_created_at_index');
        });
    }
};

