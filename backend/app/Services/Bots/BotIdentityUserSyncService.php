<?php

namespace App\Services\Bots;

use App\Enums\PostBotIdentity;
use App\Models\User;
use Illuminate\Support\Str;

class BotIdentityUserSyncService
{
    public function ensureBotUser(string $botIdentity): User
    {
        $profile = $this->profile($botIdentity);
        $candidateUsernames = array_values(array_unique(array_filter([
            $profile['username'],
            strtolower(trim($botIdentity)),
        ])));

        $user = User::query()
            ->where(function ($query) use ($candidateUsernames): void {
                $applied = false;

                foreach ($candidateUsernames as $username) {
                    if (!$applied) {
                        $query->where('username', $username);
                        $applied = true;
                        continue;
                    }

                    $query->orWhere('username', $username);
                }
            })
            ->orderByDesc('is_bot')
            ->orderBy('id')
            ->first();

        if (!$user) {
            return User::query()->create([
                'name' => $profile['display_name'],
                'username' => $profile['username'],
                'email' => null,
                'bio' => 'Automated bot account',
                'password' => Str::random(40),
                'is_admin' => false,
                'is_bot' => true,
                'role' => User::ROLE_BOT,
                'is_banned' => false,
                'banned_at' => null,
                'ban_reason' => null,
                'is_active' => true,
                'requires_email_verification' => false,
            ]);
        }

        $updates = [];
        if ((string) $user->username !== $profile['username']) {
            $updates['username'] = $profile['username'];
        }
        if (trim((string) $user->name) === '') {
            $updates['name'] = $profile['display_name'];
        }
        if ((string) $user->email !== '') {
            $updates['email'] = null;
        }
        if ((bool) $user->is_admin) {
            $updates['is_admin'] = false;
        }
        if (!(bool) $user->is_bot) {
            $updates['is_bot'] = true;
        }
        if ((string) $user->role !== User::ROLE_BOT) {
            $updates['role'] = User::ROLE_BOT;
        }
        if ((bool) $user->is_banned) {
            $updates['is_banned'] = false;
        }
        if ($user->banned_at !== null) {
            $updates['banned_at'] = null;
        }
        if ($user->ban_reason !== null) {
            $updates['ban_reason'] = null;
        }
        if (!(bool) $user->is_active) {
            $updates['is_active'] = true;
        }
        if ((bool) $user->requires_email_verification) {
            $updates['requires_email_verification'] = false;
        }
        if (trim((string) $user->bio) === '') {
            $updates['bio'] = 'Automated bot account';
        }

        if ($updates !== []) {
            $user->forceFill($updates)->save();
        }

        if ($user->email_verified_at !== null) {
            $user->forceFill([
                'email_verified_at' => null,
            ])->save();
        }

        return $user->fresh() ?? $user;
    }

    /**
     * @return array{username:string,display_name:string}
     */
    public function profile(string $botIdentity): array
    {
        $normalizedIdentity = strtolower(trim($botIdentity));
        $defaults = match ($normalizedIdentity) {
            PostBotIdentity::STELA->value => [
                'username' => 'stellarbot',
                'display_name' => 'Stella',
            ],
            default => [
                'username' => 'kozmobot',
                'display_name' => 'Kozmo',
            ],
        };

        $configuredUsername = strtolower(trim((string) config("bots.identities.{$normalizedIdentity}.username", $defaults['username'])));
        $configuredDisplayName = trim((string) config("bots.identities.{$normalizedIdentity}.display_name", $defaults['display_name']));

        return [
            'username' => $configuredUsername !== '' ? $configuredUsername : $defaults['username'],
            'display_name' => $configuredDisplayName !== '' ? $configuredDisplayName : $defaults['display_name'],
        ];
    }
}
