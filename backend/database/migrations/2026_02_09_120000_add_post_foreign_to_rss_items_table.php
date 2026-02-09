<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rss_items', function (Blueprint $table) {
            $table->foreign('post_id')
                ->references('id')
                ->on('posts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('rss_items', function (Blueprint $table) {
            $table->dropForeign(['post_id']);
        });
    }
};
