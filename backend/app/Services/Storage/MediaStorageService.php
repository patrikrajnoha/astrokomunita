<?php

namespace App\Services\Storage;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class MediaStorageService
{
    public function diskName(): string
    {
        return (string) config('media.disk', 'public');
    }

    public function storeAvatar(UploadedFile $file, int $userId): string
    {
        return $this->storePublicly($file, sprintf('avatars/%d', $userId));
    }

    public function storeCover(UploadedFile $file, int $userId): string
    {
        return $this->storePublicly($file, sprintf('covers/%d', $userId));
    }

    public function storePostAttachment(UploadedFile $file, int $postId): string
    {
        return $this->storePublicly($file, sprintf('posts/%d', $postId));
    }

    public function storeBlogCover(UploadedFile $file, int $userId): string
    {
        return $this->storePublicly($file, sprintf('blog-covers/%d', $userId));
    }

    public function delete(?string $path): void
    {
        if (!$path) {
            return;
        }

        $disk = Storage::disk($this->diskName());
        if ($disk->exists($path)) {
            $disk->delete($path);
        }
    }

    public function exists(string $path): bool
    {
        return Storage::disk($this->diskName())->exists($path);
    }

    public function absoluteUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        $url = Storage::disk($this->diskName())->url($path);
        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }

        $base = rtrim((string) config('app.url'), '/');
        return $base . '/' . ltrim($url, '/');
    }

    private function storePublicly(UploadedFile $file, string $directory): string
    {
        return $file->storePublicly($directory, ['disk' => $this->diskName()]);
    }
}

