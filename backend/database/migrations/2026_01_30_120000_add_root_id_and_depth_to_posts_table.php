<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('posts', 'root_id')) {
            Schema::table('posts', function (Blueprint $table) {
                $table->foreignId('root_id')
                    ->nullable()
                    ->after('parent_id')
                    ->constrained('posts')
                    ->cascadeOnDelete()
                    ->index();
            });
        }

        if (!Schema::hasColumn('posts', 'depth')) {
            Schema::table('posts', function (Blueprint $table) {
                $table->unsignedTinyInteger('depth')
                    ->default(0)
                    ->after('root_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('posts', 'root_id')) {
            Schema::table('posts', function (Blueprint $table) {
                $table->dropForeign(['root_id']);
                $table->dropIndex(['root_id']);
                $table->dropColumn('root_id');
            });
        }

        if (Schema::hasColumn('posts', 'depth')) {
            Schema::table('posts', function (Blueprint $table) {
                $table->dropColumn('depth');
            });
        }
    }
};
