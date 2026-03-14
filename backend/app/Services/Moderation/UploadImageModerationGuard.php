<?php

namespace App\Services\Moderation;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class UploadImageModerationGuard
{
    public function __construct(
        private readonly ModerationClient $moderationClient,
    ) {
    }

    public function assertUploadedFileAllowed(UploadedFile $file, string $field, string $context): void
    {
        if (!$this->shouldEnforce()) {
            return;
        }

        $mime = strtolower(trim((string) ($file->getMimeType() ?: $file->getClientMimeType())));
        if (!str_starts_with($mime, 'image/')) {
            return;
        }

        $path = $file->getRealPath();
        if (!is_string($path) || $path === '' || !is_file($path)) {
            throw ValidationException::withMessages([
                $field => 'Image moderation could not validate the uploaded file.',
            ]);
        }

        try {
            $response = $this->moderationClient->moderateImageFromPath($path);
        } catch (ModerationClientException $exception) {
            Log::warning('Image moderation upload check failed.', [
                'context' => $context,
                'field' => $field,
                'error_code' => $exception->errorCode(),
                'status' => $exception->statusCode(),
                'message' => $exception->getMessage(),
            ]);

            throw ValidationException::withMessages([
                $field => 'Image moderation is temporarily unavailable. Please try again later.',
            ]);
        } catch (Throwable $exception) {
            Log::warning('Unexpected image moderation upload check error.', [
                'context' => $context,
                'field' => $field,
                'message' => $exception->getMessage(),
            ]);

            throw ValidationException::withMessages([
                $field => 'Image moderation failed for this upload.',
            ]);
        }

        $decision = $this->resolveDecision($response);
        if ($decision === 'ok') {
            return;
        }

        $score = is_numeric($response['nsfw_score'] ?? null)
            ? round((float) $response['nsfw_score'], 3)
            : null;

        $message = $decision === 'blocked'
            ? 'Image was blocked by automated moderation.'
            : 'Image was flagged by automated moderation and cannot be published.';

        if ($score !== null) {
            $message .= sprintf(' NSFW score: %0.3f.', $score);
        }

        throw ValidationException::withMessages([
            $field => $message,
        ]);
    }

    private function shouldEnforce(): bool
    {
        if (!(bool) config('moderation.enabled', true)) {
            return false;
        }

        return (bool) config('moderation.enforce_upload_image_scan', !app()->environment('testing'));
    }

    /**
     * @param array<string, mixed> $response
     */
    private function resolveDecision(array $response): string
    {
        $decision = strtolower(trim((string) ($response['decision'] ?? '')));
        if (in_array($decision, ['ok', 'flagged', 'blocked'], true)) {
            return $decision;
        }

        $score = (float) ($response['nsfw_score'] ?? 0);
        $flagThreshold = (float) config('moderation.thresholds.image_flag_threshold', 0.60);
        $blockThreshold = (float) config('moderation.thresholds.image_block_threshold', 0.85);

        if ($score >= $blockThreshold) {
            return 'blocked';
        }

        if ($score >= $flagThreshold) {
            return 'flagged';
        }

        return 'ok';
    }
}

