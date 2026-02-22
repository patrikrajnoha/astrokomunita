<?php

namespace App\Services;

use App\Models\BlogPost;
use App\Models\Post;
use App\Models\User;
use App\Services\Storage\MediaStorageService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class UserCleanupService
{
    public function __construct(
        private readonly MediaStorageService $mediaStorage,
    ) {
    }

    public function cleanupUserMedia(User $user): void
    {
        $this->deletePath($user->avatar_path);
        $this->deletePath($user->cover_path);

        Post::query()
            ->where('user_id', $user->id)
            ->select(['id', 'attachment_path', 'attachment_web_path', 'attachment_original_path'])
            ->with(['poll.options:id,poll_id,image_path'])
            ->orderBy('id')
            ->chunkById(200, function (Collection $posts): void {
                foreach ($posts as $post) {
                    $this->deletePath($post->attachment_path);
                    $this->deletePath($post->attachment_web_path);
                    $this->deletePath($post->attachment_original_path, $this->mediaStorage->privateDiskName());

                    if ($post->poll === null) {
                        continue;
                    }

                    foreach ($post->poll->options as $option) {
                        $this->deletePath($option->image_path);
                    }
                }
            });

        BlogPost::query()
            ->where('user_id', $user->id)
            ->select(['id', 'cover_image_path'])
            ->orderBy('id')
            ->chunkById(200, function (Collection $posts): void {
                foreach ($posts as $post) {
                    $this->deletePath($post->cover_image_path);
                }
            });
    }

    private function deletePath(?string $path, ?string $diskName = null): void
    {
        if (!is_string($path) || trim($path) === '') {
            return;
        }

        try {
            $this->mediaStorage->delete($path, $diskName);
        } catch (\Throwable $e) {
            Log::warning('User media cleanup failed for file path.', [
                'path' => $path,
                'disk' => $diskName,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
