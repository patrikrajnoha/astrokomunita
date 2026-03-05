<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $hasAvatarMode = Schema::hasColumn('users', 'avatar_mode');
        $hasAvatarColor = Schema::hasColumn('users', 'avatar_color');
        $hasAvatarIcon = Schema::hasColumn('users', 'avatar_icon');
        $hasAvatarSeed = Schema::hasColumn('users', 'avatar_seed');

        if ($hasAvatarMode && $hasAvatarColor && $hasAvatarIcon && $hasAvatarSeed) {
            return;
        }

        Schema::table('users', function (Blueprint $table) use ($hasAvatarMode, $hasAvatarColor, $hasAvatarIcon, $hasAvatarSeed): void {
            if (!$hasAvatarMode) {
                $table->string('avatar_mode', 16)->default('image');
            }

            if (!$hasAvatarColor) {
                $table->unsignedTinyInteger('avatar_color')->nullable();
            }

            if (!$hasAvatarIcon) {
                $table->unsignedTinyInteger('avatar_icon')->nullable();
            }

            if (!$hasAvatarSeed) {
                $table->string('avatar_seed', 120)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'avatar_seed')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('avatar_seed');
            });
        }

        if (Schema::hasColumn('users', 'avatar_icon')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('avatar_icon');
            });
        }

        if (Schema::hasColumn('users', 'avatar_color')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('avatar_color');
            });
        }

        if (Schema::hasColumn('users', 'avatar_mode')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('avatar_mode');
            });
        }
    }
};
