<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use LogicException;

class DefaultUsersSeeder extends Seeder
{
    public const DEFAULT_ADMIN_NAME = 'Astrokomunita';
    public const DEFAULT_ADMIN_USERNAME = 'astrokomunita';
    public const DEFAULT_ADMIN_EMAIL = 'admin@example.com';
    public const DEFAULT_ADMIN_PASSWORD = 'CHANGE_THIS_IN_ENV';
    public const KOZMOBOT_USERNAME = 'kozmobot';
    public const STELLARBOT_USERNAME = 'stellarbot';

    /**
     * @return list<string>
     */
    public static function coreUsernames(): array
    {
        return [
            self::DEFAULT_ADMIN_USERNAME,
            self::KOZMOBOT_USERNAME,
            self::STELLARBOT_USERNAME,
        ];
    }

    /**
     * @return array{created:array<int,string>,updated:array<int,string>,deleted:array<int,string>}
     */
    public function seed(?bool $purgeNonCoreUsers = null, bool $allowProduction = false): array
    {
        $this->guardAgainstProductionExecution($allowProduction);

        if ($purgeNonCoreUsers === null) {
            $purgeNonCoreUsers = app()->environment(['local', 'testing']);
        }

        $adminEmail = trim((string) env('SEED_ADMIN_EMAIL', self::DEFAULT_ADMIN_EMAIL));
        $adminPassword = (string) env('SEED_ADMIN_PASSWORD', self::DEFAULT_ADMIN_PASSWORD);

        $this->guardAgainstProductionPurge($purgeNonCoreUsers);
        $this->guardAgainstUnsafeProductionAdminCredentials($adminEmail, $adminPassword);

        $this->normalizeLegacyBotAlias();

        $created = [];
        $updated = [];

        $defaults = [
            [
                'name' => self::DEFAULT_ADMIN_NAME,
                'username' => self::DEFAULT_ADMIN_USERNAME,
                'email' => $adminEmail,
                'password' => $adminPassword,
                'is_admin' => true,
                'is_bot' => false,
                'role' => User::ROLE_ADMIN,
            ],
            [
                'name' => 'Kozmo',
                'username' => self::KOZMOBOT_USERNAME,
                'email' => null,
                'password' => Str::random(40),
                'is_admin' => false,
                'is_bot' => true,
                'role' => User::ROLE_BOT,
            ],
            [
                'name' => 'Stella',
                'username' => self::STELLARBOT_USERNAME,
                'email' => null,
                'password' => Str::random(40),
                'is_admin' => false,
                'is_bot' => true,
                'role' => User::ROLE_BOT,
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

        $deleted = $purgeNonCoreUsers ? $this->purgeNonCoreUsers() : [];

        return [
            'created' => $created,
            'updated' => $updated,
            'deleted' => $deleted,
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

    private function guardAgainstProductionExecution(bool $allowProduction): void
    {
        if (! app()->environment('production')) {
            return;
        }

        if ($allowProduction) {
            return;
        }

        throw new LogicException('DefaultUsersSeeder refuses to run in production without explicit opt-in.');
    }

    private function guardAgainstProductionPurge(bool $purgeNonCoreUsers): void
    {
        if (! app()->environment('production')) {
            return;
        }

        if (! $purgeNonCoreUsers) {
            return;
        }

        throw new LogicException('DefaultUsersSeeder refuses to purge non-core users in production.');
    }

    private function guardAgainstUnsafeProductionAdminCredentials(string $adminEmail, string $adminPassword): void
    {
        if (! app()->environment('production')) {
            return;
        }

        $normalizedEmail = mb_strtolower($adminEmail);

        if (
            $normalizedEmail === ''
            || $normalizedEmail === mb_strtolower(self::DEFAULT_ADMIN_EMAIL)
            || str_contains($adminEmail, 'CHANGE_THIS')
        ) {
            throw new LogicException('DefaultUsersSeeder refuses to run in production with placeholder admin email.');
        }

        if ($adminPassword === '' || $adminPassword === self::DEFAULT_ADMIN_PASSWORD || str_contains($adminPassword, 'CHANGE_THIS')) {
            throw new LogicException('DefaultUsersSeeder refuses to run in production with placeholder admin password.');
        }
    }

    private function normalizeLegacyBotAlias(): void
    {
        $legacy = User::query()->where('username', 'astrobot')->first();
        if (! $legacy) {
            return;
        }

        $stellar = User::query()->where('username', self::STELLARBOT_USERNAME)->first();
        if (! $stellar) {
            $legacy->forceFill([
                'name' => 'Stella',
                'username' => self::STELLARBOT_USERNAME,
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

    /**
     * @return list<string>
     */
    private function purgeNonCoreUsers(): array
    {
        $usersToDelete = User::query()
            ->where(function ($query): void {
                $query->whereNull('username')
                    ->orWhereNotIn('username', self::coreUsernames());
            })
            ->get(['id', 'username']);

        if ($usersToDelete->isEmpty()) {
            return [];
        }

        $deletedUsernames = $usersToDelete
            ->map(static function (User $user): string {
                $username = trim((string) $user->username);

                return $username !== '' ? $username : 'id:'.$user->id;
            })
            ->all();

        User::query()
            ->whereIn('id', $usersToDelete->pluck('id')->all())
            ->delete();

        return $deletedUsernames;
    }
}
