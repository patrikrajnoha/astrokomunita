<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('users') || !Schema::hasColumn('users', 'name')) {
            return;
        }

        DB::table('users')
            ->where(function ($query): void {
                $query->where('is_bot', true)
                    ->orWhere('role', User::ROLE_BOT);
            })
            ->where('username', 'stellarbot')
            ->where(function ($query): void {
                $query->whereNull('name')
                    ->orWhere('name', '')
                    ->orWhereIn('name', ['Stela', 'Stellar', 'Stellar Bot']);
            })
            ->update([
                'name' => 'Stella',
                'updated_at' => now(),
            ]);

        DB::table('users')
            ->where(function ($query): void {
                $query->where('is_bot', true)
                    ->orWhere('role', User::ROLE_BOT);
            })
            ->where('username', 'kozmobot')
            ->where(function ($query): void {
                $query->whereNull('name')
                    ->orWhere('name', '')
                    ->orWhereIn('name', ['Kozmo Bot', 'KozmoBot', 'Kozmo bot']);
            })
            ->update([
                'name' => 'Kozmo',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Intentionally no-op: previous display names cannot be restored reliably.
    }
};
