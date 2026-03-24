<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class BotAvatarResolver
{
    public static function isBotAccount(array|User|null $user): bool
    {
        if ($user instanceof User) {
            return $user->isBot();
        }

        if (!is_array($user)) {
            return false;
        }

        return (bool) ($user['is_bot'] ?? false) || strtolower((string) ($user['role'] ?? '')) === User::ROLE_BOT;
    }

    public static function normalizedUsername(array|User|null $user): string
    {
        if ($user instanceof User) {
            return strtolower(trim((string) $user->username));
        }

        if (!is_array($user)) {
            return '';
        }

        return strtolower(trim((string) ($user['username'] ?? '')));
    }

    /**
     * @return array{prefix:string,default:string,files:array<int,string>}|null
     */
    public static function botConfig(string $username): ?array
    {
        $normalized = strtolower(trim($username));
        if ($normalized === '') {
            return null;
        }

        $bots = (array) config('bot_avatars.bots', []);
        $rawConfig = $bots[$normalized] ?? null;
        if (!is_array($rawConfig)) {
            return null;
        }

        $prefix = strtolower(trim((string) ($rawConfig['prefix'] ?? '')));
        $default = trim((string) ($rawConfig['default'] ?? ''));
        $files = array_values(array_filter(array_map(
            static fn (mixed $value): string => trim((string) $value),
            (array) ($rawConfig['files'] ?? [])
        )));

        if ($default === '' && $files !== []) {
            $default = $files[0];
        }

        if ($default === '') {
            return null;
        }

        if ($files === []) {
            $files = [$default];
        } elseif (!in_array($default, $files, true)) {
            array_unshift($files, $default);
        }

        return [
            'prefix' => $prefix,
            'default' => $default,
            'files' => $files,
        ];
    }

    public static function normalizeRelativePath(?string $path): string
    {
        $value = trim((string) ($path ?? ''));
        if ($value === '') {
            return '';
        }

        $normalized = str_replace('\\', '/', $value);
        $normalized = ltrim($normalized, '/');
        $normalized = preg_replace('#/+#', '/', $normalized) ?: '';

        return trim($normalized);
    }

    public static function defaultPathForUsername(string $username): ?string
    {
        $normalized = strtolower(trim($username));
        if ($normalized === '') {
            return null;
        }

        $botConfig = self::botConfig($normalized);
        if ($botConfig === null) {
            return null;
        }

        return sprintf('bots/%s/%s', $normalized, $botConfig['default']);
    }

    public static function isValidBotAvatarPath(?string $path, ?string $username = null): bool
    {
        if (!self::isBotAssetPath($path, $username)) {
            return false;
        }

        $normalizedPath = self::normalizeRelativePath($path);

        return self::assetFileExists($normalizedPath);
    }

    public static function isBotAssetPath(?string $path, ?string $username = null): bool
    {
        $normalizedPath = self::normalizeRelativePath($path);
        if ($normalizedPath === '' || str_contains($normalizedPath, '..')) {
            return false;
        }

        if (!Str::startsWith($normalizedPath, 'bots/')) {
            return false;
        }

        $parts = explode('/', $normalizedPath);
        if (count($parts) !== 3) {
            return false;
        }

        [$root, $botName, $file] = $parts;
        if ($root !== 'bots') {
            return false;
        }

        if (!preg_match('/^[a-z0-9._-]+$/i', $botName)) {
            return false;
        }

        if (!preg_match('/^[a-z0-9._ -]+\.png$/i', $file)) {
            return false;
        }

        if ($username !== null && strtolower(trim($username)) !== strtolower($botName)) {
            return false;
        }

        return true;
    }

    public static function resolveBotAvatarPath(array|User|null $user, ?string $rawPath = null): ?string
    {
        if (!self::isBotAccount($user)) {
            return self::normalizeRelativePath($rawPath);
        }

        $username = self::normalizedUsername($user);
        if ($username === '') {
            return null;
        }

        $path = self::normalizeRelativePath($rawPath);
        if ($path === '' || str_contains($path, '..')) {
            return self::defaultPathForUsername($username);
        }

        if (self::isValidBotAvatarPath($path, $username)) {
            return $path;
        }

        // If it points to a bot asset but is invalid/wrong-bot, always recover to default.
        if (self::isBotAssetPath($path)) {
            return self::defaultPathForUsername($username);
        }

        // Keep uploaded/stored custom avatar paths (e.g. avatars/{id}/file.png).
        return $path;
    }

    public static function isDefaultBotAvatarPath(array|User|null $user, ?string $path): bool
    {
        if (!self::isBotAccount($user)) {
            return false;
        }

        $default = self::defaultPathForUsername(self::normalizedUsername($user));
        if ($default === null) {
            return false;
        }

        return self::normalizeRelativePath($path) === $default;
    }

    public static function routeUrlForBotAssetPath(?string $relativePath): ?string
    {
        $path = self::normalizeRelativePath($relativePath);
        if (!self::isBotAssetPath($path)) {
            return null;
        }

        [$root, $username, $file] = explode('/', $path, 3);

        return url(sprintf('/api/bot-avatars/%s/%s', rawurlencode($username), rawurlencode($file)));
    }

    public static function legacyAssetUrlFromRelativePath(?string $relativePath): ?string
    {
        $path = self::normalizeRelativePath($relativePath);
        if ($path === '') {
            return null;
        }

        return url('/assets/' . $path);
    }

    public static function publicUrlCandidatesForRelativePath(?string $relativePath): array
    {
        $path = self::normalizeRelativePath($relativePath);
        if ($path === '') {
            return [];
        }

        $urls = [];
        $botRouteUrl = self::routeUrlForBotAssetPath($path);
        if ($botRouteUrl !== null) {
            $urls[] = $botRouteUrl;
            $urls[] = self::legacyAssetUrlFromRelativePath($path);
        } else {
            $urls[] = self::legacyAssetUrlFromRelativePath($path);
        }

        return array_values(array_filter(array_unique($urls)));
    }

    public static function publicUrlWithFallbackFromRelativePath(?string $relativePath): ?string
    {
        $candidates = self::publicUrlCandidatesForRelativePath($relativePath);
        if ($candidates === []) {
            return null;
        }

        return $candidates[0];
    }

    public static function toBotAssetApiPath(?string $relativePath): ?string
    {
        $path = self::normalizeRelativePath($relativePath);
        if (!self::isBotAssetPath($path)) {
            return null;
        }

        [$root, $username, $file] = explode('/', $path, 3);

        return sprintf('/api/bot-avatars/%s/%s', rawurlencode($username), rawurlencode($file));
    }

    public static function publicUrlFromRelativePath(?string $relativePath): ?string
    {
        $path = self::normalizeRelativePath($relativePath);
        if ($path === '') {
            return null;
        }

        if (self::isBotAssetPath($path)) {
            return self::routeUrlForBotAssetPath($path) ?? self::legacyAssetUrlFromRelativePath($path);
        }

        return self::legacyAssetUrlFromRelativePath($path);
    }

    public static function ensurePersistedBotAvatarPath(User $user): ?string
    {
        if (!$user->isBot()) {
            return self::normalizeRelativePath((string) ($user->avatar_path ?? ''));
        }

        $resolved = self::resolveBotAvatarPath($user, (string) ($user->avatar_path ?? ''));
        if ($resolved === null) {
            return null;
        }

        if ((string) $user->avatar_path !== $resolved) {
            $user->avatar_path = $resolved;
        }

        if ((string) $user->avatar_mode !== 'image') {
            $user->avatar_mode = 'image';
        }

        return $resolved;
    }

    /**
     * @return list<string>
     */
    public static function availableFilesForUsername(string $username): array
    {
        $normalized = strtolower(trim($username));
        if ($normalized === '') {
            return [];
        }

        $config = self::botConfig($normalized);
        if ($config === null) {
            return [];
        }

        $existing = [];
        foreach ($config['files'] as $file) {
            $path = sprintf('bots/%s/%s', $normalized, $file);
            if (self::assetFileExists($path)) {
                $existing[] = $file;
            }
        }

        if ($existing !== []) {
            return $existing;
        }

        $dir = self::assetDirectoryAbsolutePath(sprintf('bots/%s', $normalized));
        if ($dir === null || !is_dir($dir)) {
            return [];
        }

        return collect(File::files($dir))
            ->map(static fn (\SplFileInfo $file): string => $file->getFilename())
            ->filter(static fn (string $name): bool => preg_match('/\.png$/i', $name) === 1)
            ->values()
            ->all();
    }

    public static function assetAbsolutePath(?string $relativePath): ?string
    {
        $path = self::normalizeRelativePath($relativePath);
        if ($path === '' || str_contains($path, '..')) {
            return null;
        }

        $absolute = self::assetDirectoryAbsolutePath($path);
        if ($absolute === null) {
            return null;
        }

        return $absolute;
    }

    public static function assetFileExists(?string $relativePath): bool
    {
        $absolutePath = self::assetAbsolutePath($relativePath);
        if ($absolutePath === null) {
            return false;
        }

        return is_file($absolutePath);
    }

    private static function assetDirectoryAbsolutePath(string $relativePath): ?string
    {
        $normalizedPath = self::normalizeRelativePath($relativePath);
        if ($normalizedPath === '' || str_contains($normalizedPath, '..')) {
            return null;
        }

        $assetRoot = self::assetRootAbsolutePath();
        if ($assetRoot === null) {
            return null;
        }

        return rtrim($assetRoot, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalizedPath);
    }

    private static function assetRootAbsolutePath(): ?string
    {
        $configuredRoot = trim((string) config('bot_avatars.asset_root', 'assets'));
        if ($configuredRoot === '') {
            return null;
        }

        $candidates = self::assetRootCandidates($configuredRoot);

        foreach ($candidates as $candidate) {
            if (!is_dir($candidate)) {
                continue;
            }

            $botsDir = rtrim($candidate, '/\\') . DIRECTORY_SEPARATOR . 'bots';
            if (is_dir($botsDir)) {
                return $candidate;
            }
        }

        foreach ($candidates as $candidate) {
            if (is_dir($candidate)) {
                return $candidate;
            }
        }

        return $candidates[0] ?? null;
    }

    /**
     * @return list<string>
     */
    private static function assetRootCandidates(string $configuredRoot): array
    {
        $normalized = str_replace('\\', '/', trim($configuredRoot));
        if ($normalized === '') {
            return [];
        }

        $normalized = preg_replace('#/+#', '/', $normalized) ?: '';
        $normalized = preg_replace('#^\./#', '', $normalized) ?: '';

        if ($normalized === '') {
            return [];
        }

        $roots = [];
        if (self::isAbsolutePath($normalized)) {
            $roots[] = $normalized;
        } else {
            $relativeRoot = trim($normalized, '/');
            if ($relativeRoot !== '') {
                $roots[] = base_path($relativeRoot);
                $roots[] = dirname(base_path()) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeRoot);
            }
        }

        return collect($roots)
            ->map(static fn (string $path): string => rtrim(str_replace('\\', '/', $path), '/'))
            ->filter(static fn (string $path): bool => $path !== '')
            ->unique()
            ->values()
            ->all();
    }

    private static function isAbsolutePath(string $path): bool
    {
        if (Str::startsWith($path, ['/','\\'])) {
            return true;
        }

        return preg_match('/^[a-zA-Z]:[\/\\\\]/', $path) === 1;
    }
}
