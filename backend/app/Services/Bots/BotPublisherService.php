<?php

namespace App\Services\Bots;

use App\Enums\BotPublishStatus;
use App\Enums\PostAuthorKind;
use App\Enums\PostFeedKey;
use App\Models\BotItem;
use App\Models\BotSource;
use App\Models\Post;
use App\Services\Bots\Concerns\ManagesBotPublisherInternals;
use App\Services\PostService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

class BotPublisherService
{
    use ManagesBotPublisherInternals;

    public function __construct(
        private readonly PostService $postService,
        private readonly BotActivityLogService $activityLogService,
        private readonly BotRateLimiterService $rateLimiterService,
        private readonly BotIdentityUserSyncService $botIdentityUserSyncService,
    ) {
    }

    public function publishItemToAstroFeed(BotItem $item, string $runContext = 'manual'): PublishResult
    {
        $publishPayload = $this->resolvePublishPayload($item);
        $resolvedSourceUrl = $this->canonicalItemUrlForPublish($item);
        $publishStatus = $item->publish_status?->value ?? (string) $item->publish_status;
        $normalizedRunContext = $this->normalizeRunContext($runContext);

        if ($item->post_id) {
            $this->markPublishedLinkedItem($item, 'already_linked_post', $publishPayload['used_translation'], $normalizedRunContext);
            $this->logPublishActivity($item, 'skipped', 'already_linked_post', $normalizedRunContext);
            return PublishResult::skipped('already_linked_post');
        }

        if ($publishStatus === BotPublishStatus::SKIPPED->value) {
            $reason = trim((string) data_get($item->meta, 'skip_reason', ''));
            if (!$this->isRetryableSkippedReason($reason)) {
                $skipReason = $reason !== '' ? $reason : 'already_skipped';
                $this->markSkipped($item, $skipReason, $publishPayload['used_translation'], $normalizedRunContext);
                $this->logPublishActivity($item, 'skipped', $skipReason, $normalizedRunContext);

                return PublishResult::skipped($skipReason);
            }
        }

        $this->repairRecoverablePublishFields($item, $publishPayload, $resolvedSourceUrl, $normalizedRunContext);
        $skipReason = $this->resolveSkipReason($item, $publishPayload, $resolvedSourceUrl);
        if ($skipReason !== null) {
            $this->markSkipped($item, $skipReason, $publishPayload['used_translation'], $normalizedRunContext);
            $this->logPublishActivity($item, 'skipped', $skipReason, $normalizedRunContext);

            return PublishResult::skipped($skipReason);
        }

        $source = $item->source()->firstOrFail();
        $botIdentity = $item->bot_identity?->value ?? (string) $item->bot_identity;
        $publishRateLimit = $this->rateLimiterService->resolvePublishState($botIdentity);
        if (($publishRateLimit['limited'] ?? false) === true) {
            $reason = 'publish_rate_limited';
            $retryAfter = max(1, (int) ($publishRateLimit['retry_after_sec'] ?? 0));
            $this->markSkipped(
                $item,
                $reason,
                $publishPayload['used_translation'],
                $normalizedRunContext,
                [
                    'retry_after_sec' => $retryAfter,
                    'rate_limit_window_sec' => (int) ($publishRateLimit['window_sec'] ?? 0),
                    'rate_limit_max' => (int) ($publishRateLimit['max_attempts'] ?? 0),
                ]
            );
            $this->logPublishActivity(
                $item,
                'skipped',
                $reason,
                $normalizedRunContext,
                null,
                [
                    'retry_after_sec' => $retryAfter,
                    'rate_limit_window_sec' => (int) ($publishRateLimit['window_sec'] ?? 0),
                    'rate_limit_max' => (int) ($publishRateLimit['max_attempts'] ?? 0),
                ]
            );

            return PublishResult::skipped($reason);
        }

        $sourceName = $this->sourceNameForPost($source->key);
        $sourceUid = $this->sourceUidForPost($source->key, $item->stable_key);
        $postMeta = $this->buildPostMeta($source, $item, $publishPayload, $normalizedRunContext);

        $existingPost = Post::query()
            ->where('source_name', $sourceName)
            ->where('source_uid', $sourceUid)
            ->first();

        if ($existingPost) {
            if (!$existingPost->bot_item_id) {
                $existingPost->forceFill([
                    'bot_item_id' => $item->id,
                ])->save();
            }
            if (!$existingPost->ingested_at) {
                $existingPost->forceFill([
                    'ingested_at' => now(),
                ])->save();
            }

            $item->forceFill([
                'post_id' => $existingPost->id,
                'publish_status' => BotPublishStatus::PUBLISHED->value,
                'meta' => $this->withPublishAudit($item->meta, $existingPost->id, 'already_published_by_source_uid', $publishPayload['used_translation'], $normalizedRunContext),
            ])->save();

            $this->logPublishActivity($item, 'skipped', 'already_published_by_source_uid', $normalizedRunContext, $existingPost->id);

            return PublishResult::skipped('already_published_by_source_uid');
        }

        $attachment = null;
        $temporaryAttachmentPath = null;
        if ($this->isStelaIdentity($botIdentity) && $this->shouldDownloadStelaAttachment($item)) {
            $downloaded = $this->downloadStelaAttachment($item);
            if (($downloaded['error'] ?? null) !== null) {
                $reason = (string) $downloaded['error'];
                $this->markSkipped($item, $reason, $publishPayload['used_translation'], $normalizedRunContext);
                $this->logPublishActivity($item, 'skipped', $reason, $normalizedRunContext);
                return PublishResult::skipped($reason);
            }

            $attachment = $downloaded['attachment'];
            $temporaryAttachmentPath = $downloaded['temporary_path'];
        }

        $botUser = $this->botIdentityUserSyncService->ensureBotUser($botIdentity);
        $content = $this->buildPostContent(
            $publishPayload['title'],
            $publishPayload['body'],
            $resolvedSourceUrl,
            $botIdentity,
            $item,
            $source
        );
        if (config('app.debug')) {
            $currentMeta = is_array($item->meta) ? $item->meta : [];
            Log::debug('Bot publisher payload before createPost.', [
                'source_key' => strtolower(trim((string) $source->key)),
                'stable_key' => (string) $item->stable_key,
                'bot_run_id' => $item->run_id ?? data_get($currentMeta, 'last_seen_run_id'),
                'translation_status' => $item->translation_status?->value ?? (string) $item->translation_status,
                'provider' => $item->translation_provider ?? data_get($currentMeta, 'translation.provider'),
                'origin_title_hash' => $this->shortHash((string) $item->title),
                'origin_body_hash' => $this->shortHash((string) ($item->content ?: $item->summary ?: '')),
                'translated_title_hash' => $this->shortHash((string) $item->title_translated),
                'translated_body_hash' => $this->shortHash((string) $item->content_translated),
                'publish_title_hash' => $this->shortHash((string) $publishPayload['title']),
                'publish_body_hash' => $this->shortHash((string) $publishPayload['body']),
                'used_translation' => (bool) $publishPayload['used_translation'],
            ]);
        }

        try {
            try {
                $post = $this->postService->createPost($botUser, $content, $attachment, null, [
                    'feed_key' => PostFeedKey::ASTRO->value,
                    'author_kind' => PostAuthorKind::BOT->value,
                    'bot_identity' => $botIdentity,
                    'source_name' => $sourceName,
                    'source_url' => $postMeta['source_url'] ?? $resolvedSourceUrl,
                    'source_uid' => $sourceUid,
                    'bot_item_id' => $item->id,
                    'source_published_at' => $item->published_at,
                    'ingested_at' => now(),
                    'expires_at' => null,
                    'meta' => $postMeta,
                ]);
            } catch (QueryException $e) {
                $post = Post::query()
                    ->where('source_name', $sourceName)
                    ->where('source_uid', $sourceUid)
                    ->first();

                if (!$post) {
                    throw $e;
                }

                if (!$post->bot_item_id) {
                    $post->forceFill([
                        'bot_item_id' => $item->id,
                    ])->save();
                }
                if (!$post->ingested_at) {
                    $post->forceFill([
                        'ingested_at' => now(),
                    ])->save();
                }
            }
        } finally {
            $this->cleanupTemporaryFile($temporaryAttachmentPath);
        }

        $this->syncPostTranslationAudit($post, $item, $postMeta);

        $item->forceFill([
            'post_id' => $post->id,
            'publish_status' => BotPublishStatus::PUBLISHED->value,
            'meta' => $this->withPublishAudit($item->meta, $post->id, null, $publishPayload['used_translation'], $normalizedRunContext),
        ])->save();

        $this->rateLimiterService->consume($publishRateLimit);

        $this->logPublishActivity($item, 'published', null, $normalizedRunContext, $post->id, [
            'source_key' => strtolower(trim((string) $source->key)),
        ]);

        return PublishResult::published($post);
    }

    public function syncLinkedPostFromItem(BotItem $item, string $runContext = 'admin_backfill'): bool
    {
        $postId = $item->post_id ? (int) $item->post_id : 0;
        if ($postId <= 0) {
            return false;
        }

        $post = Post::query()->find($postId);
        if (!$post) {
            return false;
        }

        $source = $item->source()->firstOrFail();
        $botIdentity = $item->bot_identity?->value ?? (string) $item->bot_identity;
        $publishPayload = $this->resolvePublishPayload($item);
        $currentMeta = is_array($post->meta) ? $post->meta : [];
        $postMeta = $this->buildPostMeta($source, $item, $publishPayload, $this->normalizeRunContext($runContext));
        $currentPublishedAt = $this->nullableString(data_get($currentMeta, 'published_at_utc'));
        if ($currentPublishedAt !== null) {
            $postMeta['published_at_utc'] = $currentPublishedAt;
        }
        $content = $this->buildPostContent(
            $publishPayload['title'],
            $publishPayload['body'],
            trim((string) ($item->url ?? '')),
            $botIdentity,
            $item,
            $source
        );

        $desiredOriginalTitle = $this->nullableString($item->title);
        $desiredOriginalBody = $this->nullableString($item->content ?: $item->summary);
        $desiredTranslatedTitle = $this->nullableString($item->title_translated);
        $desiredTranslatedBody = $this->nullableString($item->content_translated);
        $desiredTranslationStatus = strtolower(trim((string) ($item->translation_status?->value ?? $item->translation_status)));
        $desiredTranslationError = $this->nullableString($item->translation_error);
        $desiredTranslatedAt = $item->translated_at;
        $desiredSourceUrl = $postMeta['source_url'] ?? $item->url;

        $contentChanged = trim((string) $post->content) !== trim((string) $content);
        $translationFieldsChanged = $this->nullableString($post->original_title) !== $desiredOriginalTitle
            || $this->nullableString($post->original_body) !== $desiredOriginalBody
            || $this->nullableString($post->translated_title) !== $desiredTranslatedTitle
            || $this->nullableString($post->translated_body) !== $desiredTranslatedBody
            || strtolower(trim((string) $post->translation_status)) !== $desiredTranslationStatus
            || $this->nullableString($post->translation_error) !== $desiredTranslationError
            || (($post->translated_at?->toIso8601String() ?? null) !== ($desiredTranslatedAt?->toIso8601String() ?? null));
        $metaChanged = $currentMeta !== $postMeta;
        $sourceUrlChanged = $this->nullableString($post->source_url) !== $this->nullableString($desiredSourceUrl);

        if (!$contentChanged && !$translationFieldsChanged && !$metaChanged && !$sourceUrlChanged) {
            return false;
        }

        $post->forceFill([
            'content' => $content,
            'source_url' => $desiredSourceUrl,
            'original_title' => $desiredOriginalTitle,
            'original_body' => $desiredOriginalBody,
            'translated_title' => $desiredTranslatedTitle,
            'translated_body' => $desiredTranslatedBody,
            'translation_status' => $desiredTranslationStatus !== '' ? $desiredTranslationStatus : null,
            'translation_error' => $desiredTranslationError,
            'translated_at' => $desiredTranslatedAt,
            'meta' => $postMeta,
        ])->save();

        if (config('app.debug')) {
            Log::debug('Bot publisher backfill updated linked post.', [
                'source_key' => strtolower(trim((string) $source->key)),
                'stable_key' => (string) $item->stable_key,
                'post_id' => $post->id,
                'translation_status' => $desiredTranslationStatus,
                'provider' => $item->translation_provider ?? data_get($postMeta, 'translation.provider'),
                'used_translation' => (bool) ($publishPayload['used_translation'] ?? false),
            ]);
        }

        return true;
    }

    private function buildPostContent(
        string $headline,
        string $body,
        string $url,
        string $botIdentity,
        BotItem $item,
        BotSource $source
    ): string
    {
        $sourceKey = strtolower(trim((string) $source->key));
        $sourceLabel = $this->sourceLabelForPostMeta($sourceKey);
        $sourceAttribution = $this->sourceAttributionForPostMeta($sourceKey);

        if ($this->isStelaIdentity($botIdentity)) {
            return $this->buildStelaPostContent($headline, $body, $url, $item, $sourceLabel);
        }

        return $this->buildKozmoPostContent($headline, $body, $url, $sourceAttribution);
    }

    private function buildKozmoPostContent(string $headline, string $body, string $url, string $sourceAttribution): string
    {
        if ($headline === '') {
            $headline = $sourceAttribution . ' update';
        }

        $normalizedBody = $this->normalizeKozmoBody($body);

        $lines = [
            sprintf('%s | %s', $sourceAttribution, $headline),
        ];

        if ($normalizedBody !== '') {
            $lines[] = '';
            $lines[] = $normalizedBody;
        }

        $bodyHasSourceLine = preg_match('/^\s*(source|zdroj)\s*:/imu', $normalizedBody) === 1;
        $bodyHasUrl = $url !== '' && str_contains($normalizedBody, $url);

        if (!$bodyHasSourceLine) {
            $lines[] = '';
            $lines[] = sprintf('Source: %s', $sourceAttribution);
        }

        if ($url !== '' && !$bodyHasUrl) {
            $lines[] = $url;
        }

        return implode("\n", $lines);
    }

    private function normalizeKozmoBody(string $body): string
    {
        $normalized = trim($body);
        if ($normalized === '') {
            return '';
        }

        // Common SK phrasing fix for generated timeline entries.
        $normalized = str_ireplace('poslednom letiaci', 'poslednom lete', $normalized);

        return $normalized;
    }

    private function buildStelaPostContent(
        string $headline,
        string $body,
        string $url,
        BotItem $item,
        string $sourceLabel
    ): string
    {
        $title = trim($headline);
        if ($title === '') {
            $title = $sourceLabel;
        }
        $title = $this->limitText($title, 300);

        $apodDate = trim((string) data_get($item->meta, 'apod_date', ''));
        $copyright = trim((string) data_get($item->meta, 'copyright', ''));

        $attributionParts = [$sourceLabel];
        if ($apodDate !== '') {
            $attributionParts[] = $apodDate;
        }
        if ($copyright !== '') {
            $attributionParts[] = 'Credit: ' . $copyright;
        }

        $attributionLine = $this->limitText('Attribution: ' . implode(' | ', $attributionParts), 500);
        $suffix = "\n\n" . $attributionLine;
        $urlLine = trim($url) !== '' ? $this->limitText($url, 500) : '';
        if ($urlLine !== '') {
            $suffix .= "\n" . $urlLine;
        }

        $prefix = $title;
        $normalizedBody = trim($body);
        if ($normalizedBody === '') {
            return $prefix . $suffix;
        }

        $maxLength = PostService::USER_CONTENT_MAX;
        $maxBodyLength = max(
            0,
            $maxLength - $this->stringLength($prefix) - $this->stringLength($suffix) - 2
        );
        $limitedBody = $this->limitText($normalizedBody, $maxBodyLength);

        if ($limitedBody === '') {
            return $prefix . $suffix;
        }

        return $prefix . "\n\n" . $limitedBody . $suffix;
    }

    private function sourceNameForPost(string $sourceKey): string
    {
        return substr('bot_' . $sourceKey, 0, 50);
    }

    private function sourceUidForPost(string $sourceKey, string $stableKey): string
    {
        return sha1($sourceKey . '|' . $stableKey);
    }

    /**
     * @param array{title:string,body:string,used_translation:bool} $publishPayload
     * @return array<string,mixed>
     */
    private function buildPostMeta(BotSource $source, BotItem $item, array $publishPayload, string $runContext): array
    {
        $botIdentity = strtolower(trim((string) ($item->bot_identity?->value ?? $item->bot_identity)));
        $sourceKey = strtolower(trim((string) $source->key));
        $translationStatus = strtolower(trim((string) ($item->translation_status?->value ?? $item->translation_status)));
        $translationAudit = $this->translationAuditForItem($item);
        $translationProvider = trim((string) ($translationAudit['provider'] ?? ''));

        return [
            'bot_identity' => $botIdentity !== '' ? $botIdentity : null,
            'bot_source_key' => $sourceKey !== '' ? $sourceKey : null,
            'bot_source_label' => $this->sourceLabelForPostMeta($sourceKey),
            'bot_source_attribution' => $this->sourceAttributionForPostMeta($sourceKey),
            'source_attribution' => $this->sourceAttributionForPostMeta($sourceKey),
            'source_url' => $this->canonicalSourceUrl($source, $item),
            'published_by' => 'bot-engine',
            'published_at_utc' => now()->utc()->toIso8601String(),
            'run_context' => $runContext,
            'original_title' => $this->nullableString($item->title),
            'original_content' => $this->nullableString($item->content ?: $item->summary),
            'translated_title' => $this->nullableString($item->title_translated),
            'translated_content' => $this->nullableString($item->content_translated),
            'translation_status' => $translationStatus !== '' ? $translationStatus : null,
            'used_translation' => (bool) ($publishPayload['used_translation'] ?? false),
            'translation_provider' => $translationProvider !== '' ? $translationProvider : null,
            'translation' => $translationAudit,
        ];
    }
}
