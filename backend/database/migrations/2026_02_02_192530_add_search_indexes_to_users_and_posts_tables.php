<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Indexy pre vyhľadávanie používateľov
        Schema::table('users', function (Blueprint $table) {
            $table->index(['username'], 'users_username_index');
            $table->index(['name'], 'users_name_index');
            $table->index(['is_active', 'is_banned'], 'users_active_banned_index');
        });

        // Index pre vyhľadávanie v obsahu príspevkov
        Schema::table('posts', function (Blueprint $table) {
            $table->index(['content'], 'posts_content_index');
            $table->index(['parent_id', 'is_hidden'], 'posts_parent_hidden_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_username_index');
            $table->dropIndex('users_name_index');
            $table->dropIndex('users_active_banned_index');
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->dropIndex('posts_content_index');
            $table->dropIndex('posts_parent_hidden_index');
        });
    }
};
