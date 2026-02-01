<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->string('source_name', 50)->nullable()->after('attachment_size');
            $table->string('source_url', 500)->nullable()->after('source_name');
            $table->string('source_uid', 191)->nullable()->after('source_url');
            $table->timestamp('source_published_at')->nullable()->after('source_uid');

            $table->unique(['source_name', 'source_uid'], 'posts_source_unique');
            $table->index('source_published_at', 'posts_source_published_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropUnique('posts_source_unique');
            $table->dropIndex('posts_source_published_at_index');
            $table->dropColumn([
                'source_name',
                'source_url',
                'source_uid',
                'source_published_at',
            ]);
        });
    }
};
