<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table): void {
            $table->timestamp('profile_pinned_at')->nullable()->after('pinned_at');
            $table->index(['user_id', 'profile_pinned_at'], 'posts_user_profile_pinned_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table): void {
            $table->dropIndex('posts_user_profile_pinned_at_index');
            $table->dropColumn('profile_pinned_at');
        });
    }
};

