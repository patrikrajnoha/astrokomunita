<?php

namespace App\Support;

use App\Models\User;
use App\Services\Storage\MediaStorageService;

class PublicUserPayload
{
    /**
     * @var list<string>
     */
    private const ALLOWED_KEYS = [
        'id',
        'name',
        'username',
        'bio',
        'location',
        'location_label',
        'is_admin',
        'is_bot',
        'role',
        'avatar_path',
        'cover_path',
        'avatar_mode',
        'avatar_color',
        'avatar_icon',
        'avatar_seed',
    ];

    public function __construct(
        private readonly MediaStorageService $mediaStorage,
    ) {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function fromUser(?User $user): ?array
    {
        if (!$user) {
            return null;
        }

        return $this->fromArray($user->toArray());
    }

    /**
     * @param array<string, mixed>|null $userData
     * @return array<string, mixed>|null
     */
    public function fromArray(?array $userData): ?array
    {
        if (!is_array($userData) || $userData === []) {
            return null;
        }

        $payload = [];

        foreach (self::ALLOWED_KEYS as $key) {
            if (array_key_exists($key, $userData)) {
                $payload[$key] = $userData[$key];
            }
        }

        $location = trim((string) ($payload['location'] ?? ''));
        $locationLabel = trim((string) ($payload['location_label'] ?? ''));
        if ($location === '' && $locationLabel !== '') {
            $payload['location'] = $locationLabel;
        }

        if (BotAvatarResolver::isBotAccount($payload)) {
            $payload['avatar_path'] = BotAvatarResolver::resolveBotAvatarPath(
                $payload,
                (string) ($payload['avatar_path'] ?? '')
            );
            $payload['avatar_mode'] = 'image';
            $payload['avatar_color'] = null;
            $payload['avatar_icon'] = null;
            $payload['avatar_seed'] = null;
        }

        $payload['avatar_url'] = $this->resolveMediaUrl(
            $userData['avatar_url'] ?? null,
            $payload['avatar_path'] ?? null,
            $payload,
        );
        $payload['cover_url'] = $this->resolveMediaUrl(
            $userData['cover_url'] ?? null,
            $payload['cover_path'] ?? null
        );

        return $payload;
    }

    /**
     * @param array<string,mixed> $userData
     */
    private function resolveMediaUrl(mixed $existingUrl, mixed $path, array $userData = []): ?string
    {
        $url = trim((string) ($existingUrl ?? ''));
        if ($url !== '') {
            return $url;
        }

        $normalizedPath = trim((string) ($path ?? ''));
        if ($normalizedPath === '') {
            if (BotAvatarResolver::isBotAccount($userData)) {
                $resolvedBotPath = BotAvatarResolver::resolveBotAvatarPath($userData, null);
                return BotAvatarResolver::publicUrlFromRelativePath($resolvedBotPath);
            }
            return null;
        }

        if (BotAvatarResolver::isBotAccount($userData)) {
            $resolvedBotPath = BotAvatarResolver::resolveBotAvatarPath($userData, $normalizedPath);
            return BotAvatarResolver::publicUrlFromRelativePath($resolvedBotPath);
        }

        if (!$this->mediaStorage->exists($normalizedPath)) {
            return null;
        }

        return $this->mediaStorage->absoluteUrl($normalizedPath);
    }
}
