<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'avatar_mode')) {
                $table->string('avatar_mode', 20)->default('image')->after('cover_path');
            }

            if (! Schema::hasColumn('users', 'avatar_color')) {
                $table->unsignedTinyInteger('avatar_color')->nullable()->after('avatar_mode');
            }

            if (! Schema::hasColumn('users', 'avatar_icon')) {
                $table->unsignedTinyInteger('avatar_icon')->nullable()->after('avatar_color');
            }

            if (! Schema::hasColumn('users', 'avatar_seed')) {
                $table->string('avatar_seed', 80)->nullable()->after('avatar_icon');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $columnsToDrop = [];

            foreach (['avatar_mode', 'avatar_color', 'avatar_icon', 'avatar_seed'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $columnsToDrop[] = $column;
                }
            }

            if ($columnsToDrop !== []) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
