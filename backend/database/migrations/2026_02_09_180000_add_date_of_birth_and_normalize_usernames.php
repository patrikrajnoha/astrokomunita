<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const USERNAME_PATTERN = '/^[a-z][a-z0-9_]{2,19}$/';

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->date('date_of_birth')->nullable()->after('username');
        });

        $reserved = array_map(
            static fn (string $item): string => strtolower(trim($item)),
            (array) config('auth.username.reserved', [])
        );

        $used = [];

        DB::table('users')
            ->select(['id', 'username'])
            ->orderBy('id')
            ->each(function (object $user) use (&$used, $reserved): void {
                $normalized = $this->normalizeUsername($user->username, (int) $user->id, $reserved);
                $unique = $this->makeUniqueUsername($normalized, (int) $user->id, $used);

                DB::table('users')
                    ->where('id', $user->id)
                    ->update(['username' => $unique]);

                $used[$unique] = true;
            });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('date_of_birth');
        });
    }

    private function normalizeUsername(?string $username, int $userId, array $reserved): string
    {
        $normalized = strtolower(trim((string) $username));
        $normalized = preg_replace('/[^a-z0-9_]/', '', $normalized) ?? '';
        $normalized = preg_replace('/_+/', '_', $normalized) ?? '';
        $normalized = trim($normalized, '_');

        if ($normalized === '' || !preg_match('/^[a-z]/', $normalized)) {
            $normalized = 'user_' . $userId;
        }

        $normalized = substr($normalized, 0, 20);

        if (strlen($normalized) < 3) {
            $normalized = str_pad($normalized, 3, 'x');
        }

        if (!preg_match(self::USERNAME_PATTERN, $normalized) || in_array($normalized, $reserved, true)) {
            $normalized = 'user_' . $userId;
        }

        return substr($normalized, 0, 20);
    }

    private function makeUniqueUsername(string $base, int $userId, array $used): string
    {
        $candidate = $base;
        $suffix = 1;

        while (isset($used[$candidate]) || $this->existsForOtherUser($candidate, $userId)) {
            $postfix = '_' . $suffix;
            $candidate = substr($base, 0, 20 - strlen($postfix)) . $postfix;
            $suffix++;
        }

        return $candidate;
    }

    private function existsForOtherUser(string $username, int $userId): bool
    {
        return DB::table('users')
            ->where('username', $username)
            ->where('id', '!=', $userId)
            ->exists();
    }
};
