<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\User;
use App\Services\Storage\MediaStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class MediaViewController extends Controller
{
    public function __construct(
        private readonly MediaStorageService $mediaStorage,
    ) {
    }

    public function __invoke(Request $request, Post $media)
    {
        $viewer = $request->user() ?? $request->user('sanctum');
        if (!$this->canViewMedia($viewer, $media)) {
            return response()->json([
                'message' => 'Zakazane.',
            ], 403);
        }

        $path = $media->attachment_web_path ?: $media->attachment_path;
        if (!$path) {
            return response()->json([
                'message' => 'Nenaslo sa.',
            ], 404);
        }

        $disk = Storage::disk($this->mediaStorage->publicDiskName());
        if (!$disk->exists($path)) {
            return response()->json([
                'message' => 'Nenaslo sa.',
            ], 404);
        }

        $mime = (string) ($media->attachment_web_mime ?: $media->attachment_mime ?: 'application/octet-stream');

        return $disk->response($path, null, [
            'Content-Type' => $mime,
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    private function canViewMedia(?User $viewer, Post $post): bool
    {
        $attachmentStatus = strtolower(trim((string) ($post->attachment_moderation_status ?? '')));
        $attachmentRestricted = $post->attachment_hidden_at !== null
            || in_array($attachmentStatus, ['pending', 'flagged', 'blocked'], true);

        if ($viewer) {
            return Gate::forUser($viewer)->allows('viewRestricted', $post) || (
                !$post->is_hidden
                && $post->hidden_at === null
                && $post->moderation_status !== 'blocked'
                && !$attachmentRestricted
            );
        }

        return !$post->is_hidden
            && $post->hidden_at === null
            && $post->moderation_status !== 'blocked'
            && !$attachmentRestricted;
    }
}

