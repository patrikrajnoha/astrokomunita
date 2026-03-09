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

    public function publicDiskName(): string
    {
        return $this->diskName();
    }

    public function privateDiskName(): string
    {
        return (string) config('media.private_disk', 'local');
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

    public function storePollOptionImage(UploadedFile $file, int $pollId, int $optionId): string
    {
        return $this->storePublicly($file, sprintf('polls/%d/options/%d', $pollId, $optionId));
    }

    public function storeBlogCover(UploadedFile $file, int $userId): string
    {
        return $this->storePublicly($file, sprintf('blog-covers/%d', $userId));
    }

    public function storeObservationImage(UploadedFile $file, int $observationId): string
    {
        return $this->storePublicly($file, sprintf('observations/%d/images', $observationId));
    }

    public function storeSidebarWidgetImage(UploadedFile $file, int $userId): string
    {
        return $this->storePublicly($file, sprintf('sidebar-widgets/%d', $userId));
    }

    public function delete(?string $path, ?string $diskName = null): void
    {
        if (!$path) {
            return;
        }

        $disk = Storage::disk($diskName ?: $this->diskName());
        if ($disk->exists($path)) {
            $disk->delete($path);
        }
    }

    public function exists(string $path, ?string $diskName = null): bool
    {
        return Storage::disk($diskName ?: $this->diskName())->exists($path);
    }

    public function absoluteUrl(?string $path, ?string $diskName = null): ?string
    {
        if (!$path) {
            return null;
        }

        $resolvedDiskName = $diskName ?: $this->diskName();
        $diskDriver = (string) config(sprintf('filesystems.disks.%s.driver', $resolvedDiskName), '');
        if ($diskDriver === 'local') {
            return $this->publicMediaApiUrl($path);
        }

        $url = Storage::disk($resolvedDiskName)->url($path);
        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }

        $base = rtrim((string) config('app.url'), '/');
        return $base . '/' . ltrim($url, '/');
    }

    private function publicMediaApiUrl(string $path): string
    {
        $normalizedPath = trim(str_replace('\\', '/', $path), '/');
        $encodedPath = implode('/', array_map('rawurlencode', explode('/', $normalizedPath)));
        return '/api/media/file/' . $encodedPath;
    }

    private function storePublicly(UploadedFile $file, string $directory): string
    {
        return $file->storePublicly($directory, ['disk' => $this->diskName()]);
    }

    public function writePublic(string $path, string $contents): void
    {
        Storage::disk($this->publicDiskName())->put($path, $contents, ['visibility' => 'public']);
    }

    public function writePrivate(string $path, string $contents): void
    {
        Storage::disk($this->privateDiskName())->put($path, $contents);
    }

    /**
     * @param resource $stream
     */
    public function writePrivateStream(string $path, $stream): void
    {
        Storage::disk($this->privateDiskName())->put($path, $stream);
    }
}
