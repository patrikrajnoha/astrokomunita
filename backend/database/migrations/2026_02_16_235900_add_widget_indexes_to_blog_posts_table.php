<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->index(['views', 'created_at'], 'blog_posts_views_created_at_idx');
            $table->index('created_at', 'blog_posts_created_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->dropIndex('blog_posts_views_created_at_idx');
            $table->dropIndex('blog_posts_created_at_idx');
        });
    }
};
