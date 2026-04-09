<?php

namespace App\Services\Moderation;

use App\Models\ModerationLog;
use App\Models\Post;
use App\Services\Storage\MediaStorageService;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ModerationService
{
    public function __construct(
        private readonly ModerationClient $client,
        private readonly MediaStorageService $mediaStorage,
    ) {
    }

    public function moderatePost(Post $post): void
    {
        $textResult = $this->moderatePostText($post);
        $mediaResult = $this->moderatePostAttachment($post);

        $combinedDecision = $this->combineDecisions([
            $textResult['decision'],
            $mediaResult['decision'],
        ]);

        $this->applyPostDecision($post, $combinedDecision, [
            'text' => $textResult['summary'],
            'attachment' => $mediaResult['summary'],
            'combined_decision' => $combinedDecision,
            'evaluated_at' => now()->toIso8601String(),
        ]);
    }

    private function moderatePostText(Post $post): array
    {
        $content = trim((string) $post->content);
        if ($content === '') {
            return [
                'decision' => 'ok',
                'summary' => [
                    'toxicity_score' => 0,
                    'hate_score' => 0,
                    'decision' => 'ok',
                ],
            ];
        }

        $startedAt = microtime(true);

        try {
            $response = $this->client->moderateText($content);
        } catch (ModerationClientException $exception) {
            $this->writeLog(
                entityType: 'post',
                entityId: (int) $post->id,
                decision: 'flagged',
                scores: [],
                labels: [],
                modelVersions: [],
                latencyMs: $this->latencyMs($startedAt),
                errorCode: $exception->errorCode(),
                requestHash: hash('sha256', $content),
                requestExcerpt: mb_substr($content, 0, 120)
            );

            throw new ModerationTemporaryException($exception->getMessage(), previous: $exception);
        }

        $toxicityScore = (float) ($response['toxicity_score'] ?? 0);
        $hateScore = (float) ($response['hate_score'] ?? 0);
        $maxScore = max($toxicityScore, $hateScore);

        $decision = $this->decisionFromScore(
            $maxScore,
            (float) config('moderation.thresholds.text_flag_threshold', 0.7),
            (float) config('moderation.thresholds.text_block_threshold', 0.9)
        );

        $summary = [
            'decision' => $decision,
            'toxicity_score' => $toxicityScore,
            'hate_score' => $hateScore,
            'scores' => $response['scores'] ?? [],
            'labels' => $response['labels'] ?? [],
            'model_versions' => $response['model_versions'] ?? [],
        ];

        $this->writeLog(
            entityType: 'post',
            entityId: (int) $post->id,
            decision: $decision,
            scores: [
                'toxicity_score' => $toxicityScore,
                'hate_score' => $hateScore,
                'max_score' => $maxScore,
            ],
            labels: (array) ($response['labels'] ?? []),
            modelVersions: (array) ($response['model_versions'] ?? []),
            latencyMs: $this->latencyMs($startedAt),
            errorCode: null,
            requestHash: hash('sha256', $content),
            requestExcerpt: mb_substr($content, 0, 120)
        );

        return [
            'decision' => $decision,
            'summary' => $summary,
        ];
    }

    private function moderatePostAttachment(Post $post): array
    {
        if ((!$post->attachment_path && !$post->attachment_web_path) || !$this->isImageAttachment($post)) {
            return [
                'decision' => 'ok',
                'summary' => [
                    'decision' => 'ok',
                    'nsfw_score' => 0,
                    'skipped' => true,
                ],
            ];
        }

        $disk = Storage::disk($this->mediaStorage->diskName());

        // Prefer the web-optimised variant (already resized ≤1600 px, compressed) to avoid
        // payload_too_large errors when sending a full-resolution original to the moderation service.
        $storagePath = (filled($post->attachment_web_path) && $disk->exists($post->attachment_web_path))
            ? (string) $post->attachment_web_path
            : (string) $post->attachment_path;

        if (!$disk->exists($storagePath)) {
            $this->writeLog(
                entityType: 'media',
                entityId: (int) $post->id,
                decision: 'flagged',
                scores: [],
                labels: [],
                modelVersions: [],
                latencyMs: 0,
                errorCode: 'attachment_missing',
                requestHash: null,
                requestExcerpt: $post->attachment_original_name
            );

            throw new ModerationTemporaryException('Attachment file missing for moderation.');
        }

        $startedAt = microtime(true);
        $cleanupPaths = [];
        $sourceCleanupPath = null;
        $absolutePath = $this->resolveAttachmentPathForModeration($disk, $storagePath, $sourceCleanupPath);
        if (is_string($sourceCleanupPath) && $sourceCleanupPath !== '') {
            $cleanupPaths[] = $sourceCleanupPath;
        }

        $payloadLimitBytes = $this->moderationImageMaxBytes();
        $resizedForModeration = false;
        $pathForModeration = $absolutePath;
        $sourceSize = $this->safeFileSize($absolutePath);

        if ($sourceSize !== null && $sourceSize > $payloadLimitBytes) {
            $reducedPath = $this->createReducedAttachmentForModeration($absolutePath, $payloadLimitBytes);
            if (is_string($reducedPath) && $reducedPath !== '') {
                $cleanupPaths[] = $reducedPath;
                $pathForModeration = $reducedPath;
                $resizedForModeration = true;
            }
        }

        try {
            try {
                $response = $this->client->moderateImageFromPath($pathForModeration);
            } catch (ModerationClientException $exception) {
                if ($this->isPayloadTooLargeError($exception)) {
                    $reducedPath = $this->createReducedAttachmentForModeration($pathForModeration, $payloadLimitBytes);
                    if (is_string($reducedPath) && $reducedPath !== '') {
                        $cleanupPaths[] = $reducedPath;
                        $resizedForModeration = true;
                        $response = $this->client->moderateImageFromPath($reducedPath);
                    } else {
                        throw $exception;
                    }
                } else {
                    throw $exception;
                }
            }
        } catch (ModerationClientException $exception) {
            $this->writeLog(
                entityType: 'media',
                entityId: (int) $post->id,
                decision: 'flagged',
                scores: [],
                labels: [],
                modelVersions: [],
                latencyMs: $this->latencyMs($startedAt),
                errorCode: $exception->errorCode(),
                requestHash: hash('sha256', $storagePath),
                requestExcerpt: $post->attachment_original_name
            );

            throw new ModerationTemporaryException($exception->getMessage(), previous: $exception);
        } finally {
            $this->cleanupTemporaryFiles($cleanupPaths);
        }

        $nsfwScore = (float) ($response['nsfw_score'] ?? 0);

        $decision = $this->decisionFromScore(
            $nsfwScore,
            (float) config('moderation.thresholds.image_flag_threshold', 0.6),
            (float) config('moderation.thresholds.image_block_threshold', 0.85)
        );

        $summary = [
            'decision' => $decision,
            'nsfw_score' => $nsfwScore,
            'scores' => $response['scores'] ?? [],
            'labels' => $response['labels'] ?? [],
            'model_versions' => $response['model_versions'] ?? [],
        ];
        if ($resizedForModeration) {
            $summary['input_resized_for_moderation'] = true;
        }

        $this->writeLog(
            entityType: 'media',
            entityId: (int) $post->id,
            decision: $decision,
            scores: [
                'nsfw_score' => $nsfwScore,
            ],
            labels: (array) ($response['labels'] ?? []),
            modelVersions: (array) ($response['model_versions'] ?? []),
            latencyMs: $this->latencyMs($startedAt),
            errorCode: null,
            requestHash: hash('sha256', $post->attachment_path),
            requestExcerpt: $post->attachment_original_name
        );

        return [
            'decision' => $decision,
            'summary' => $summary,
        ];
    }

    private function applyPostDecision(Post $post, string $decision, array $summary): void
    {
        $updates = [
            'moderation_status' => $decision,
            'moderation_summary' => $summary,
        ];

        $updates['is_hidden'] = $decision === 'blocked';
        $updates['hidden_reason'] = $decision === 'blocked'
            ? 'blocked_by_automated_moderation'
            : null;
        $updates['hidden_at'] = $decision === 'blocked' ? now() : null;

        if ($post->attachment_path && $this->isImageAttachment($post)) {
            $attachmentDecision = (string) ($summary['attachment']['decision'] ?? 'ok');
            $updates['attachment_moderation_status'] = $attachmentDecision;
            $updates['attachment_moderation_summary'] = $summary['attachment'] ?? null;
            $updates['attachment_is_blurred'] = in_array($attachmentDecision, ['pending', 'blocked'], true);
            $updates['attachment_hidden_at'] = $attachmentDecision === 'blocked' ? now() : null;
        } else {
            $updates['attachment_moderation_status'] = null;
            $updates['attachment_moderation_summary'] = null;
            $updates['attachment_is_blurred'] = false;
            $updates['attachment_hidden_at'] = null;
        }

        $post->forceFill($updates)->save();
        event(new \App\Events\PostUpdated($post));
    }

    private function writeLog(
        string $entityType,
        int $entityId,
        string $decision,
        array $scores,
        array $labels,
        array $modelVersions,
        int $latencyMs,
        ?string $errorCode,
        ?string $requestHash,
        ?string $requestExcerpt,
    ): void {
        ModerationLog::query()->create([
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'decision' => $decision,
            'scores' => $scores,
            'labels' => $labels,
            'model_versions' => $modelVersions,
            'latency_ms' => $latencyMs,
            'error_code' => $errorCode,
            'request_hash' => $requestHash,
            'request_excerpt' => $requestExcerpt,
        ]);
    }

    private function decisionFromScore(float $score, float $flagThreshold, float $blockThreshold): string
    {
        if ($score >= $blockThreshold) {
            return 'blocked';
        }

        if ($score >= $flagThreshold) {
            return 'flagged';
        }

        return 'ok';
    }

    private function combineDecisions(array $decisions): string
    {
        if (in_array('blocked', $decisions, true)) {
            return 'blocked';
        }

        if (in_array('flagged', $decisions, true)) {
            return 'flagged';
        }

        return 'ok';
    }

    private function isImageAttachment(Post $post): bool
    {
        $mime = strtolower((string) ($post->attachment_mime ?? ''));
        if (str_starts_with($mime, 'image/')) {
            return true;
        }

        $name = strtolower((string) ($post->attachment_original_name ?? $post->attachment_path ?? ''));
        return str_ends_with($name, '.jpg')
            || str_ends_with($name, '.jpeg')
            || str_ends_with($name, '.png')
            || str_ends_with($name, '.webp')
            || str_ends_with($name, '.gif');
    }

    private function latencyMs(float $startedAt): int
    {
        return (int) max(0, round((microtime(true) - $startedAt) * 1000));
    }

    private function moderationImageMaxBytes(): int
    {
        $configured = (int) config('moderation.image_max_bytes', 32 * 1024 * 1024);
        return $configured > 0 ? $configured : (32 * 1024 * 1024);
    }

    private function isPayloadTooLargeError(ModerationClientException $exception): bool
    {
        if ($exception->statusCode() === 413) {
            return true;
        }

        return strtolower(trim($exception->errorCode())) === 'payload_too_large';
    }

    private function createReducedAttachmentForModeration(string $sourcePath, int $targetBytes): ?string
    {
        $attempts = [
            [
                'max_width' => max(320, (int) config('moderation.image_resize_max_width', 1600)),
                'jpeg_quality' => max(25, min(95, (int) config('moderation.image_resize_jpeg_quality', 78))),
            ],
            ['max_width' => 1280, 'jpeg_quality' => 62],
            ['max_width' => 960, 'jpeg_quality' => 50],
            ['max_width' => 720, 'jpeg_quality' => 42],
        ];

        $sourceSize = $this->safeFileSize($sourcePath) ?? PHP_INT_MAX;
        $bestPath = null;
        $bestSize = PHP_INT_MAX;

        foreach ($attempts as $attempt) {
            $maxWidth = (int) ($attempt['max_width'] ?? 0);
            $jpegQuality = (int) ($attempt['jpeg_quality'] ?? 78);

            $candidatePath = $this->buildReducedAttachmentWithImagick($sourcePath, $maxWidth, $jpegQuality)
                ?? $this->buildReducedAttachmentWithGd($sourcePath, $maxWidth, $jpegQuality);

            if (!is_string($candidatePath) || $candidatePath === '') {
                continue;
            }

            $candidateSize = $this->safeFileSize($candidatePath) ?? PHP_INT_MAX;
            if ($candidateSize < $bestSize) {
                if (is_string($bestPath) && $bestPath !== '' && is_file($bestPath)) {
                    @unlink($bestPath);
                }
                $bestPath = $candidatePath;
                $bestSize = $candidateSize;
            } else {
                if (is_file($candidatePath)) {
                    @unlink($candidatePath);
                }
            }

            if ($candidateSize <= $targetBytes) {
                return $bestPath;
            }
        }

        if (is_string($bestPath) && $bestPath !== '' && $bestSize < $sourceSize) {
            return $bestPath;
        }

        if (is_string($bestPath) && $bestPath !== '' && is_file($bestPath)) {
            @unlink($bestPath);
        }

        return null;
    }

    private function buildReducedAttachmentWithImagick(string $sourcePath, int $maxWidth, int $jpegQuality): ?string
    {
        if (!extension_loaded('imagick') || !class_exists(\Imagick::class)) {
            return null;
        }

        try {
            $image = new \Imagick();
            $image->readImage($sourcePath);
            $image->setIteratorIndex(0);
            $image->autoOrient();

            $sourceWidth = (int) $image->getImageWidth();
            if ($maxWidth > 0 && $sourceWidth > $maxWidth) {
                $image->resizeImage($maxWidth, 0, \Imagick::FILTER_LANCZOS, 1, true);
            }

            $image->setImageBackgroundColor('white');
            $flattened = $image->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
            if ($flattened instanceof \Imagick) {
                $image->clear();
                $image->destroy();
                $image = $flattened;
            }

            $image->stripImage();
            $image->setImageFormat('jpeg');
            $image->setImageCompressionQuality(max(20, min($jpegQuality, 95)));

            $blob = $image->getImagesBlob();
            $image->clear();
            $image->destroy();

            if ($blob === '') {
                return null;
            }

            return $this->writeTemporaryModerationFile($blob);
        } catch (Throwable) {
            return null;
        }
    }

    private function buildReducedAttachmentWithGd(string $sourcePath, int $maxWidth, int $jpegQuality): ?string
    {
        if (!function_exists('imagecreatefromstring') || !function_exists('imagejpeg')) {
            return null;
        }

        if (!$this->canRasterizeWithGd($sourcePath)) {
            return null;
        }

        $raw = @file_get_contents($sourcePath);
        if (!is_string($raw) || $raw === '') {
            return null;
        }

        $source = @imagecreatefromstring($raw);
        if ($source === false) {
            return null;
        }

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        if ($sourceWidth < 1 || $sourceHeight < 1) {
            imagedestroy($source);
            return null;
        }

        $targetWidth = $maxWidth > 0 ? min($sourceWidth, $maxWidth) : $sourceWidth;
        $targetWidth = max(1, $targetWidth);
        $targetHeight = max(1, (int) round(($targetWidth / $sourceWidth) * $sourceHeight));

        $target = imagecreatetruecolor($targetWidth, $targetHeight);
        if ($target === false) {
            imagedestroy($source);
            return null;
        }

        $white = imagecolorallocate($target, 255, 255, 255);
        imagefilledrectangle($target, 0, 0, $targetWidth, $targetHeight, $white);
        imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $sourceWidth, $sourceHeight);

        $tmpPath = tempnam(sys_get_temp_dir(), 'modimg_');
        if ($tmpPath === false) {
            imagedestroy($target);
            imagedestroy($source);
            return null;
        }

        $ok = @imagejpeg($target, $tmpPath, max(20, min($jpegQuality, 95)));
        imagedestroy($target);
        imagedestroy($source);

        if (!$ok) {
            @unlink($tmpPath);
            return null;
        }

        return $tmpPath;
    }

    private function canRasterizeWithGd(string $sourcePath): bool
    {
        $dimensions = @getimagesize($sourcePath);
        if (!is_array($dimensions)) {
            return false;
        }

        $width = (int) ($dimensions[0] ?? 0);
        $height = (int) ($dimensions[1] ?? 0);
        if ($width < 1 || $height < 1) {
            return false;
        }

        $maxPixels = max(
            1,
            (int) config(
                'moderation.image_resize_gd_max_pixels',
                (int) config('media.post_image_processing_max_pixels', 16000000)
            )
        );

        return ((float) $width * (float) $height) <= (float) $maxPixels;
    }

    private function writeTemporaryModerationFile(string $contents): ?string
    {
        if ($contents === '') {
            return null;
        }

        $tmpPath = tempnam(sys_get_temp_dir(), 'modimg_');
        if ($tmpPath === false) {
            return null;
        }

        $written = @file_put_contents($tmpPath, $contents);
        if (!is_int($written) || $written < 1) {
            @unlink($tmpPath);
            return null;
        }

        return $tmpPath;
    }

    private function safeFileSize(string $path): ?int
    {
        $size = @filesize($path);
        if (!is_int($size) || $size < 1) {
            return null;
        }

        return $size;
    }

    private function cleanupTemporaryFiles(array $paths): void
    {
        foreach ($paths as $path) {
            if (is_string($path) && $path !== '' && is_file($path)) {
                @unlink($path);
            }
        }
    }

    private function resolveAttachmentPathForModeration($disk, string $storagePath, ?string &$cleanupPath = null): string
    {
        $cleanupPath = null;

        try {
            $absolute = $disk->path($storagePath);
            if (is_string($absolute) && $absolute !== '' && is_file($absolute)) {
                return $absolute;
            }
        } catch (\Throwable) {
            // Disk may not provide local paths (e.g. cloud adapters).
        }

        $stream = $disk->readStream($storagePath);
        if ($stream === false || !is_resource($stream)) {
            throw new ModerationTemporaryException('Unable to read attachment stream for moderation.');
        }

        $tmpPath = tempnam(sys_get_temp_dir(), 'mod_');
        if ($tmpPath === false) {
            if (is_resource($stream)) {
                fclose($stream);
            }
            throw new ModerationTemporaryException('Unable to create temporary file for moderation.');
        }

        $tmpHandle = fopen($tmpPath, 'wb');
        if (!is_resource($tmpHandle)) {
            fclose($stream);
            @unlink($tmpPath);
            throw new ModerationTemporaryException('Unable to open temporary file for moderation.');
        }

        stream_copy_to_stream($stream, $tmpHandle);
        fclose($stream);
        fclose($tmpHandle);

        $cleanupPath = $tmpPath;
        return $tmpPath;
    }
}
