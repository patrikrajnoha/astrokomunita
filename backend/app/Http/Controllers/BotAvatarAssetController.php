<?php

namespace App\Http\Controllers;

use App\Support\BotAvatarResolver;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BotAvatarAssetController extends Controller
{
    public function __invoke(string $username, string $file): BinaryFileResponse
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
