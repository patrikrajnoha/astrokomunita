<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DefaultUsersSeeder extends Seeder
{
    /**
     * @return array{created:array<int,string>,updated:array<int,string>}
     */
    public function seed(): array
    {
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
                'name' => 'AstroBot',
                'username' => 'astrobot',
                'email' => 'astrobot@astrobot.sk',
                'password' => 'astrobot',
                'is_admin' => false,
                'is_bot' => true,
                'role' => 'user',
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
            $email = (string) $defaultUser['email'];
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
            $user->forceFill([
                'email_verified_at' => now(),
            ])->save();

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

    private function findExistingUser(string $email, string $username): ?User
    {
        $userByEmail = User::query()
            ->where('email', $email)
            ->first();

        if ($userByEmail !== null) {
            return $userByEmail;
        }

        return User::query()
            ->where('username', $username)
            ->first();
    }

    public function run(): void
    {
        $this->seed();
    }
}
