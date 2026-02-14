<?php

namespace App\Services\Moderation;

use App\Models\ModerationLog;
use App\Models\Post;
use App\Services\Storage\MediaStorageService;
use Illuminate\Support\Facades\Storage;

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
        if (!$post->attachment_path || !$this->isImageAttachment($post)) {
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
        if (!$disk->exists($post->attachment_path)) {
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
        $cleanupPath = null;
        $absolutePath = $this->resolveAttachmentPathForModeration($disk, $post->attachment_path, $cleanupPath);

        try {
            $response = $this->client->moderateImageFromPath($absolutePath);
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
                requestHash: hash('sha256', $post->attachment_path),
                requestExcerpt: $post->attachment_original_name
            );

            throw new ModerationTemporaryException($exception->getMessage(), previous: $exception);
        } finally {
            if (is_string($cleanupPath) && $cleanupPath !== '' && is_file($cleanupPath)) {
                @unlink($cleanupPath);
            }
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
