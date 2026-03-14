<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Observation;
use App\Models\ObservationMedia;
use App\Models\Post;
use App\Models\User;
use App\Services\Storage\MediaStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PublicMediaFileController extends Controller
{
    public function __construct(
        private readonly MediaStorageService $mediaStorage,
    ) {
    }

    public function __invoke(Request $request, string $path)
    {
        $viewer = $request->user() ?? $request->user('sanctum');

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

        if (!$this->canViewPublicPath($viewer, $normalizedPath)) {
            return response()->json([
                'message' => 'Zakazane.',
            ], 403);
        }

        $mime = (string) ($disk->mimeType($normalizedPath) ?: 'application/octet-stream');

        return $disk->response($normalizedPath, null, [
            'Content-Type' => $mime,
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    private function canViewPublicPath(?User $viewer, string $path): bool
    {
        if ($viewer?->isAdmin()) {
            return true;
        }

        $post = Post::query()
            ->select([
                'id',
                'is_hidden',
                'hidden_at',
                'moderation_status',
                'attachment_moderation_status',
                'attachment_hidden_at',
            ])
            ->where(function ($query) use ($path): void {
                $query->where('attachment_path', $path)
                    ->orWhere('attachment_web_path', $path)
                    ->orWhere('attachment_original_path', $path);
            })
            ->first();

        if ($post) {
            $attachmentStatus = strtolower(trim((string) ($post->attachment_moderation_status ?? '')));
            $attachmentRestricted = $post->attachment_hidden_at !== null
                || in_array($attachmentStatus, ['pending', 'flagged', 'blocked'], true);

            return !$post->is_hidden
                && $post->hidden_at === null
                && $post->moderation_status !== 'blocked'
                && !$attachmentRestricted;
        }

        $observationMedia = ObservationMedia::query()
            ->select(['id', 'observation_id'])
            ->where('path', $path)
            ->first();

        if (!$observationMedia) {
            return true;
        }

        $observation = Observation::query()
            ->select(['id', 'user_id', 'is_public'])
            ->find((int) $observationMedia->observation_id);

        if (!$observation) {
            return false;
        }

        if ((bool) $observation->is_public) {
            return true;
        }

        if (!$viewer) {
            return false;
        }

        return (int) $viewer->id === (int) $observation->user_id;
    }
}

