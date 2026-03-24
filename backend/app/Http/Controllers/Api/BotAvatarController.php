<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\BotAvatarResolver;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BotAvatarController extends Controller
{
    public function index(string $username): JsonResponse
    {
        $normalized = strtolower(trim($username));
        $config = BotAvatarResolver::botConfig($normalized);
        if ($config === null) {
            return response()->json([
                'username' => $normalized,
                'files' => [],
                'default' => null,
            ]);
        }

        $files = BotAvatarResolver::availableFilesForUsername($normalized);
        $default = in_array($config['default'], $files, true)
            ? $config['default']
            : ($files[0] ?? $config['default']);

        $items = array_map(
            static fn (string $file): array => [
                'file' => $file,
                'path' => sprintf('bots/%s/%s', $normalized, $file),
                'url' => BotAvatarResolver::publicUrlFromRelativePath(sprintf('bots/%s/%s', $normalized, $file)),
            ],
            $files
        );

        return response()->json([
            'username' => $normalized,
            'default' => $default,
            'files' => $items,
        ]);
    }

    public function show(string $username, string $file): BinaryFileResponse
    {
        $relativePath = sprintf('bots/%s/%s', strtolower(trim($username)), trim($file));

        if (!BotAvatarResolver::isValidBotAvatarPath($relativePath, $username)) {
            abort(404);
        }

        $absolutePath = BotAvatarResolver::assetAbsolutePath($relativePath);
        if ($absolutePath === null || !is_file($absolutePath)) {
            abort(404);
        }

        return response()->file($absolutePath, [
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
