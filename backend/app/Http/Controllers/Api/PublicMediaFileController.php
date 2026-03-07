<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Storage\MediaStorageService;
use Illuminate\Support\Facades\Storage;

class PublicMediaFileController extends Controller
{
    public function __construct(
        private readonly MediaStorageService $mediaStorage,
    ) {
    }

    public function __invoke(string $path)
    {
        $normalizedPath = trim(str_replace('\\', '/', $path), '/');
        if ($normalizedPath === '' || str_contains($normalizedPath, '..')) {
            return response()->json([
                'message' => 'Nenaslo sa.',
            ], 404);
        }

        $disk = Storage::disk($this->mediaStorage->publicDiskName());
        if (!$disk->exists($normalizedPath)) {
            return response()->json([
                'message' => 'Nenaslo sa.',
            ], 404);
        }

        $mime = (string) ($disk->mimeType($normalizedPath) ?: 'application/octet-stream');

        return $disk->response($normalizedPath, null, [
            'Content-Type' => $mime,
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}

