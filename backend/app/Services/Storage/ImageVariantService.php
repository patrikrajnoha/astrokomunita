<?php

namespace App\Services\Storage;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class ImageVariantService
{
    public function __construct(
        private readonly MediaStorageService $mediaStorage,
    ) {
    }

    /**
     * @return array{
     *   original_path: string,
     *   web_path: string,
     *   original_mime: string,
     *   web_mime: string,
     *   original_size: int,
     *   web_size: int,
     *   width: int|null,
     *   height: int|null,
     *   variants_json: array<string, mixed>
     * }
     */
    public function storePostImageVariants(UploadedFile $uploadedFile, int $postId, int $mediaId, int $userId): array
    {
        $originalMime = $this->resolveMime($uploadedFile);
        if (!$this->isAllowedImageMime($originalMime)) {
            throw ValidationException::withMessages([
                'attachment' => 'Unsupported image format.',
            ]);
        }

        $baseDirectory = sprintf('posts/%d/images/%d', $postId, $mediaId);
        $originalExtension = $this->extensionForMime($originalMime) ?? $this->normalizeExtension($uploadedFile->getClientOriginalExtension()) ?? 'bin';
        $originalPath = sprintf('%s/original.%s', $baseDirectory, $originalExtension);

        $sourcePath = $uploadedFile->getRealPath();
        if (!$sourcePath) {
            throw new RuntimeException('Uploaded image temporary file is missing.');
        }

        $stream = fopen($sourcePath, 'rb');
        if ($stream === false) {
            throw new RuntimeException('Unable to read uploaded image stream.');
        }

        try {
            $this->mediaStorage->writePrivateStream($originalPath, $stream);
        } finally {
            fclose($stream);
        }

        return $this->buildAndStorePostImageVariants(
            sourcePath: $sourcePath,
            originalMime: $originalMime,
            originalPath: $originalPath,
            postId: $postId,
            mediaId: $mediaId,
            userId: $userId,
            originalSize: (int) ($uploadedFile->getSize() ?? 0),
        );
    }

    /**
     * Rebuild the public web variant from an already stored original image.
     *
     * @return array{
     *   original_path: string,
     *   web_path: string,
     *   original_mime: string,
     *   web_mime: string,
     *   original_size: int,
     *   web_size: int,
     *   width: int|null,
     *   height: int|null,
     *   variants_json: array<string, mixed>
     * }
     */
    public function rebuildPostImageVariantsFromExistingOriginal(
        string $originalPath,
        string $originalMime,
        int $postId,
        int $mediaId,
        int $userId
    ): array {
        $normalizedMime = $this->normalizeMime($originalMime);
        if ($normalizedMime === null || !$this->isAllowedImageMime($normalizedMime)) {
            throw ValidationException::withMessages([
                'attachment' => 'Unsupported image format.',
            ]);
        }

        $privateDisk = Storage::disk($this->mediaStorage->privateDiskName());
        if (!$privateDisk->exists($originalPath)) {
            throw new RuntimeException('Stored original image file is missing.');
        }

        $temporaryPath = $this->copyStoredFileToTemporaryPath($privateDisk, $originalPath);
        try {
            return $this->buildAndStorePostImageVariants(
                sourcePath: $temporaryPath,
                originalMime: $normalizedMime,
                originalPath: $originalPath,
                postId: $postId,
                mediaId: $mediaId,
                userId: $userId,
                originalSize: (int) ($privateDisk->size($originalPath) ?: 0),
            );
        } finally {
            @unlink($temporaryPath);
        }
    }

    public function isAllowedImageMime(?string $mime): bool
    {
        $normalized = $this->normalizeMime($mime);
        if ($normalized === null) {
            return false;
        }

        $allowed = array_map(
            fn (mixed $value): string => strtolower(trim((string) $value)),
            (array) config('media.post_image_allowed_mimes', [])
        );

        return in_array($normalized, $allowed, true);
    }

    /**
     * @return array{mime: string, extension: string, contents: string, width: int|null, height: int|null, processed: bool}
     */
    private function buildWebVariant(string $sourcePath, string $originalMime, ?int $width, ?int $height): array
    {
        if ($originalMime === 'image/gif') {
            $contents = file_get_contents($sourcePath);
            if ($contents === false) {
                throw new RuntimeException('Unable to read GIF source image.');
            }

            return [
                'mime' => 'image/gif',
                'extension' => 'gif',
                'contents' => $contents,
                'width' => $width,
                'height' => $height,
                'processed' => false,
            ];
        }

        if ($this->shouldBypassRasterProcessing($width, $height)) {
            return $this->buildUnprocessedFallback($sourcePath, $originalMime, $width, $height);
        }

        $imagickResult = $this->buildWithImagick($sourcePath, $originalMime);
        if ($imagickResult !== null) {
            return $imagickResult;
        }

        $gdResult = $this->buildWithGd($sourcePath, $originalMime);
        if ($gdResult !== null) {
            return $gdResult;
        }

        return $this->buildUnprocessedFallback($sourcePath, $originalMime, $width, $height);
    }

    /**
     * @return array{mime: string, extension: string, contents: string, width: int|null, height: int|null, processed: bool}|null
     */
    private function buildWithImagick(string $sourcePath, string $originalMime): ?array
    {
        if (!class_exists(\Imagick::class)) {
            return null;
        }

        try {
            $image = new \Imagick();
            $image->readImage($sourcePath);
            $image->setIteratorIndex(0);
            $image->autoOrient();

            $maxWidth = (int) config('media.post_image_web_max_width', 1600);
            $currentWidth = (int) $image->getImageWidth();
            if ($maxWidth > 0 && $currentWidth > $maxWidth) {
                $image->resizeImage($maxWidth, 0, \Imagick::FILTER_LANCZOS, 1, true);
            }

            $image->stripImage();

            $supportsWebp = in_array('WEBP', \Imagick::queryFormats('WEBP'), true);
            if ($supportsWebp) {
                $image->setImageFormat('webp');
                $image->setImageCompressionQuality((int) config('media.post_image_webp_quality', 80));
                $mime = 'image/webp';
                $extension = 'webp';
            } else {
                if ($this->mimeSupportsAlpha($originalMime)) {
                    $image->setImageBackgroundColor('white');
                    $image = $image->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
                }
                $image->setImageFormat('jpeg');
                $image->setImageCompressionQuality((int) config('media.post_image_jpeg_quality', 82));
                $mime = 'image/jpeg';
                $extension = 'jpg';
            }

            $blob = $image->getImagesBlob();
            $width = (int) $image->getImageWidth();
            $height = (int) $image->getImageHeight();
            $image->clear();
            $image->destroy();

            if ($blob === '') {
                return null;
            }

            return [
                'mime' => $mime,
                'extension' => $extension,
                'contents' => $blob,
                'width' => $width > 0 ? $width : null,
                'height' => $height > 0 ? $height : null,
                'processed' => true,
            ];
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array{mime: string, extension: string, contents: string, width: int|null, height: int|null, processed: bool}|null
     */
    private function buildWithGd(string $sourcePath, string $originalMime): ?array
    {
        if (!function_exists('imagecreatefromstring')) {
            return null;
        }

        $source = $this->createGdImageResource($sourcePath, $originalMime);
        if (!$source) {
            return null;
        }

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        if ($sourceWidth < 1 || $sourceHeight < 1) {
            imagedestroy($source);
            return null;
        }

        $maxWidth = (int) config('media.post_image_web_max_width', 1600);
        $targetWidth = $maxWidth > 0 ? min($sourceWidth, $maxWidth) : $sourceWidth;
        $targetHeight = (int) round(($targetWidth / $sourceWidth) * $sourceHeight);

        $target = imagecreatetruecolor($targetWidth, $targetHeight);
        if (!$target) {
            imagedestroy($source);
            return null;
        }

        $useWebp = function_exists('imagewebp');
        if ($useWebp) {
            imagealphablending($target, false);
            imagesavealpha($target, true);
            $transparent = imagecolorallocatealpha($target, 0, 0, 0, 127);
            imagefilledrectangle($target, 0, 0, $targetWidth, $targetHeight, $transparent);
            $mime = 'image/webp';
            $extension = 'webp';
        } else {
            $white = imagecolorallocate($target, 255, 255, 255);
            imagefilledrectangle($target, 0, 0, $targetWidth, $targetHeight, $white);
            $mime = 'image/jpeg';
            $extension = 'jpg';
        }

        imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $sourceWidth, $sourceHeight);

        ob_start();
        if ($useWebp) {
            imagewebp($target, null, (int) config('media.post_image_webp_quality', 80));
        } else {
            imagejpeg($target, null, (int) config('media.post_image_jpeg_quality', 82));
        }
        $contents = (string) ob_get_clean();

        imagedestroy($target);
        imagedestroy($source);

        if ($contents === '') {
            return null;
        }

        return [
            'mime' => $mime,
            'extension' => $extension,
            'contents' => $contents,
            'width' => $targetWidth,
            'height' => $targetHeight,
            'processed' => true,
        ];
    }

    /**
     * @return resource|\GdImage|false
     */
    private function createGdImageResource(string $sourcePath, string $originalMime): mixed
    {
        $source = match ($originalMime) {
            'image/jpeg' => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($sourcePath) : false,
            'image/png' => function_exists('imagecreatefrompng') ? @imagecreatefrompng($sourcePath) : false,
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($sourcePath) : false,
            default => false,
        };

        if ($source !== false) {
            return $source;
        }

        $raw = file_get_contents($sourcePath);
        if ($raw === false) {
            return false;
        }

        return @imagecreatefromstring($raw);
    }

    /**
     * @return array{width: int|null, height: int|null}
     */
    private function extractDimensions(string $sourcePath): array
    {
        $result = @getimagesize($sourcePath);
        if (!is_array($result)) {
            return ['width' => null, 'height' => null];
        }

        $width = isset($result[0]) ? (int) $result[0] : null;
        $height = isset($result[1]) ? (int) $result[1] : null;

        return [
            'width' => $width > 0 ? $width : null,
            'height' => $height > 0 ? $height : null,
        ];
    }

    private function shouldBypassRasterProcessing(?int $width, ?int $height): bool
    {
        if ($width === null || $height === null || $width < 1 || $height < 1) {
            return false;
        }

        $maxDimension = max(1, (int) config('media.post_image_max_pixels', 10000));
        if ($width > $maxDimension || $height > $maxDimension) {
            return true;
        }

        $maxTotalPixels = max(1, (int) config('media.post_image_processing_max_pixels', 16000000));
        $totalPixels = (float) $width * (float) $height;

        return $totalPixels > $maxTotalPixels;
    }

    /**
     * @return array{mime: string, extension: string, contents: string, width: int|null, height: int|null, processed: bool}
     */
    private function buildUnprocessedFallback(string $sourcePath, string $mime, ?int $width, ?int $height): array
    {
        $contents = file_get_contents($sourcePath);
        if ($contents === false) {
            throw new RuntimeException('Unable to read source image for fallback variant.');
        }

        return [
            'mime' => $mime,
            'extension' => $this->extensionForMime($mime) ?? 'jpg',
            'contents' => $contents,
            'width' => $width,
            'height' => $height,
            'processed' => false,
        ];
    }

    private function resolveMime(UploadedFile $uploadedFile): string
    {
        $mime = $this->normalizeMime($uploadedFile->getMimeType() ?: $uploadedFile->getClientMimeType());
        if ($mime === null) {
            throw ValidationException::withMessages([
                'attachment' => 'Unable to determine image MIME type.',
            ]);
        }

        return $mime;
    }

    private function normalizeMime(?string $mime): ?string
    {
        if ($mime === null) {
            return null;
        }

        $normalized = strtolower(trim($mime));
        return $normalized !== '' ? $normalized : null;
    }

    private function normalizeExtension(?string $extension): ?string
    {
        if ($extension === null) {
            return null;
        }

        $normalized = strtolower(trim($extension, " .\t\n\r\0\x0B"));
        return $normalized !== '' ? $normalized : null;
    }

    private function extensionForMime(string $mime): ?string
    {
        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            default => null,
        };
    }

    private function mimeSupportsAlpha(string $mime): bool
    {
        return in_array($mime, ['image/png', 'image/webp'], true);
    }

    /**
     * @return array{
     *   original_path: string,
     *   web_path: string,
     *   original_mime: string,
     *   web_mime: string,
     *   original_size: int,
     *   web_size: int,
     *   width: int|null,
     *   height: int|null,
     *   variants_json: array<string, mixed>
     * }
     */
    private function buildAndStorePostImageVariants(
        string $sourcePath,
        string $originalMime,
        string $originalPath,
        int $postId,
        int $mediaId,
        int $userId,
        int $originalSize = 0
    ): array {
        $baseDirectory = sprintf('posts/%d/images/%d', $postId, $mediaId);
        $dimensions = $this->extractDimensions($sourcePath);
        $variant = $this->buildWebVariant($sourcePath, $originalMime, $dimensions['width'], $dimensions['height']);

        $webPath = sprintf('%s/web.%s', $baseDirectory, $variant['extension']);
        $this->mediaStorage->writePublic($webPath, $variant['contents']);

        $privateDisk = Storage::disk($this->mediaStorage->privateDiskName());
        $publicDisk = Storage::disk($this->mediaStorage->publicDiskName());

        $resolvedOriginalSize = $originalSize > 0
            ? $originalSize
            : (int) ($privateDisk->size($originalPath) ?: 0);
        $webSize = (int) ($publicDisk->size($webPath) ?: strlen($variant['contents']));

        return [
            'original_path' => $originalPath,
            'web_path' => $webPath,
            'original_mime' => $originalMime,
            'web_mime' => $variant['mime'],
            'original_size' => $resolvedOriginalSize,
            'web_size' => $webSize,
            'width' => $variant['width'],
            'height' => $variant['height'],
            'variants_json' => [
                'original' => [
                    'path' => $originalPath,
                    'mime' => $originalMime,
                    'size' => $resolvedOriginalSize,
                ],
                'web' => [
                    'path' => $webPath,
                    'mime' => $variant['mime'],
                    'size' => $webSize,
                    'width' => $variant['width'],
                    'height' => $variant['height'],
                ],
                'processed' => $variant['processed'],
                'owner_user_id' => $userId,
            ],
        ];
    }

    private function copyStoredFileToTemporaryPath(mixed $disk, string $path): string
    {
        $stream = $disk->readStream($path);
        if ($stream === false) {
            throw new RuntimeException('Unable to read stored original image stream.');
        }

        $temporaryPath = tempnam(sys_get_temp_dir(), 'post-image-');
        if ($temporaryPath === false) {
            if (is_resource($stream)) {
                fclose($stream);
            }

            throw new RuntimeException('Unable to create temporary image file.');
        }

        $temporaryHandle = fopen($temporaryPath, 'wb');
        if ($temporaryHandle === false) {
            fclose($stream);
            @unlink($temporaryPath);
            throw new RuntimeException('Unable to open temporary image file for writing.');
        }

        try {
            stream_copy_to_stream($stream, $temporaryHandle);
        } finally {
            fclose($stream);
            fclose($temporaryHandle);
        }

        return $temporaryPath;
    }
}
