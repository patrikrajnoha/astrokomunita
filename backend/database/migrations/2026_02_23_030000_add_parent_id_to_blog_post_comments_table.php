<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('blog_post_comments', function (Blueprint $table) {
            $table->foreignId('parent_id')
                ->nullable()
                ->after('user_id')
                ->constrained('blog_post_comments')
                ->nullOnDelete();

            $table->index(['blog_post_id', 'parent_id']);
        });
    }

    public function down(): void
    {
        Schema::table('blog_post_comments', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropIndex(['blog_post_id', 'parent_id']);
            $table->dropColumn('parent_id');
        });
    }
};
