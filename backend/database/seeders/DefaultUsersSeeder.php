<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DefaultUsersSeeder extends Seeder
{
    /**
     * @return array{created:array<int,string>,updated:array<int,string>}
     */
    public function seed(): array
    {
        if (!app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('DefaultUsersSeeder can only run in local/testing environments.');
        }

        $this->normalizeLegacyBotAlias();

        $created = [];
        $updated = [];

        $defaults = [
            [
                'name' => 'Admin',
                'username' => 'admin',
                'email' => 'admin@admin.sk',
                'password' => 'admin',
                'is_admin' => true,
                'is_bot' => false,
                'role' => 'admin',
            ],
            [
                'name' => 'Kozmo',
                'username' => 'kozmobot',
                'email' => null,
                'password' => Str::random(40),
                'is_admin' => false,
                'is_bot' => true,
                'role' => User::ROLE_BOT,
            ],
            [
                'name' => 'Stellar',
                'username' => 'stellarbot',
                'email' => null,
                'password' => Str::random(40),
                'is_admin' => false,
                'is_bot' => true,
                'role' => User::ROLE_BOT,
            ],
            [
                'name' => 'Patrik',
                'username' => 'patrik',
                'email' => 'patrik@patrik.sk',
                'password' => 'patrik',
                'is_admin' => false,
                'is_bot' => false,
                'role' => 'user',
            ],
        ];

        foreach ($defaults as $defaultUser) {
            $email = isset($defaultUser['email']) && is_string($defaultUser['email'])
                ? trim($defaultUser['email'])
                : null;
            $username = (string) $defaultUser['username'];
            $user = $this->findExistingUser($email, $username);
            $alreadyExists = $user !== null;

            if ($user === null) {
                $user = new User();
            }

            $user->fill([
                'name' => (string) $defaultUser['name'],
                'username' => $username,
                'email' => $email,
                'password' => Hash::make((string) $defaultUser['password']),
                'is_admin' => (bool) $defaultUser['is_admin'],
                'is_bot' => (bool) $defaultUser['is_bot'],
                'role' => (string) $defaultUser['role'],
                'is_active' => true,
                'is_banned' => false,
            ]);
            $user->save();

            // email_verified_at is intentionally forced because User::fillable excludes it.
            if ($email !== null && $email !== '') {
                $user->forceFill([
                    'email_verified_at' => now(),
                ])->save();
            }

            if ($alreadyExists) {
                $updated[] = $username;
            } else {
                $created[] = $username;
            }
        }

        return [
            'created' => $created,
            'updated' => $updated,
        ];
    }

    private function findExistingUser(?string $email, string $username): ?User
    {
        if ($email !== null && $email !== '') {
            $userByEmail = User::query()
                ->where('email', $email)
                ->first();

            if ($userByEmail !== null) {
                return $userByEmail;
            }
        }

        return User::query()
            ->where('username', $username)
            ->first();
    }

    public function run(): void
    {
        $this->seed();
    }

    private function normalizeLegacyBotAlias(): void
    {
        $legacy = User::query()->where('username', 'astrobot')->first();
        if (! $legacy) {
            return;
        }

        $stellar = User::query()->where('username', 'stellarbot')->first();
        if (! $stellar) {
            $legacy->forceFill([
                'name' => 'Stellar',
                'username' => 'stellarbot',
                'email' => null,
                'is_bot' => true,
                'role' => User::ROLE_BOT,
                'is_admin' => false,
                'is_active' => true,
                'is_banned' => false,
                'ban_reason' => null,
                'banned_at' => null,
                'requires_email_verification' => false,
                'email_verified_at' => null,
            ])->save();
            return;
        }

        $legacy->delete();
    }
}
