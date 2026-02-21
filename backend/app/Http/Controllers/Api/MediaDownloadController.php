<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\User;
use App\Services\Storage\MediaStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaDownloadController extends Controller
{
    public function __construct(
        private readonly MediaStorageService $mediaStorage,
    ) {
    }

    public function __invoke(Request $request, Post $media)
    {
        $viewer = $request->user() ?? $request->user('sanctum');
        if (!$this->canDownloadOriginal($viewer, $media)) {
            return response()->json([
                'message' => 'Forbidden.',
            ], 403);
        }

        if (!$this->isImageAttachment($media)) {
            return response()->json([
                'message' => 'Not found.',
            ], 404);
        }

        $originalPath = $media->attachment_original_path ?: $media->attachment_path;
        if (!$originalPath) {
            return response()->json([
                'message' => 'Not found.',
            ], 404);
        }

        $diskName = $media->attachment_original_path
            ? $this->mediaStorage->privateDiskName()
            : $this->mediaStorage->publicDiskName();

        $disk = Storage::disk($diskName);
        if (!$disk->exists($originalPath)) {
            return response()->json([
                'message' => 'Not found.',
            ], 404);
        }

        $extension = strtolower((string) pathinfo($originalPath, PATHINFO_EXTENSION));
        if ($extension === '') {
            $extension = $this->extensionForMime((string) ($media->attachment_original_mime ?: $media->attachment_mime));
        }

        $downloadName = $this->buildDownloadFilename($media, $extension);
        $mime = (string) ($media->attachment_original_mime ?: $media->attachment_mime ?: 'application/octet-stream');

        return $disk->download($originalPath, $downloadName, [
            'Content-Type' => $mime,
        ]);
    }

    private function canDownloadOriginal(?User $viewer, Post $post): bool
    {
        if ($viewer) {
            return Gate::forUser($viewer)->allows('downloadOriginal', $post);
        }

        return !$post->is_hidden && $post->hidden_at === null && $post->moderation_status !== 'blocked';
    }

    private function isImageAttachment(Post $post): bool
    {
        $mime = strtolower(trim((string) ($post->attachment_original_mime ?: $post->attachment_mime)));
        return str_starts_with($mime, 'image/');
    }

    private function buildDownloadFilename(Post $post, string $extension): string
    {
        $base = pathinfo((string) ($post->attachment_original_name ?: 'post-' . $post->id), PATHINFO_FILENAME);
        $safeBase = Str::slug($base);
        if ($safeBase === '') {
            $safeBase = 'post-' . $post->id;
        }

        $normalizedExtension = trim(strtolower($extension), " .\t\n\r\0\x0B");
        if ($normalizedExtension === '') {
            $normalizedExtension = 'bin';
        }

        return sprintf('%s-original.%s', $safeBase, $normalizedExtension);
    }

    private function extensionForMime(string $mime): string
    {
        return match (strtolower(trim($mime))) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            default => 'bin',
        };
    }
}
