<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('users') || !Schema::hasColumn('users', 'avatar_path')) {
            return;
        }

        DB::table('users')
            ->where(function ($query): void {
                $query->where('is_bot', true)
                    ->orWhere('role', User::ROLE_BOT);
            })
            ->where('username', 'stellarbot')
            ->where(function ($query): void {
                $query->whereNull('avatar_path')->orWhere('avatar_path', '');
            })
            ->update([
                'avatar_path' => 'bots/stellarbot/sb_blue.png',
                'avatar_mode' => 'image',
                'avatar_color' => null,
                'avatar_icon' => null,
                'avatar_seed' => null,
                'updated_at' => now(),
            ]);

        DB::table('users')
            ->where(function ($query): void {
                $query->where('is_bot', true)
                    ->orWhere('role', User::ROLE_BOT);
            })
            ->where('username', 'kozmobot')
            ->where(function ($query): void {
                $query->whereNull('avatar_path')->orWhere('avatar_path', '');
            })
            ->update([
                'avatar_path' => 'bots/kozmobot/kb_blue.png',
                'avatar_mode' => 'image',
                'avatar_color' => null,
                'avatar_icon' => null,
                'avatar_seed' => null,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('users') || !Schema::hasColumn('users', 'avatar_path')) {
            return;
        }

        DB::table('users')
            ->where('avatar_path', 'bots/stellarbot/sb_blue.png')
            ->update([
                'avatar_path' => null,
                'updated_at' => now(),
            ]);

        DB::table('users')
            ->where('avatar_path', 'bots/kozmobot/kb_blue.png')
            ->update([
                'avatar_path' => null,
                'updated_at' => now(),
            ]);
    }
};
