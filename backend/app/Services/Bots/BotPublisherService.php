<?php

namespace App\Services\Bots;

use App\Enums\BotPublishStatus;
use App\Enums\PostAuthorKind;
use App\Enums\PostBotIdentity;
use App\Enums\PostFeedKey;
use App\Models\BotItem;
use App\Models\BotSource;
use App\Models\Post;
use App\Models\User;
use App\Services\PostService;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BotPublisherService
{
    public function __construct(
        private readonly PostService $postService,
    ) {
    }

    public function publishItemToAstroFeed(BotItem $item, string $runContext = 'manual'): PublishResult
    {
        $publishPayload = $this->resolvePublishPayload($item);
        $publishStatus = $item->publish_status?->value ?? (string) $item->publish_status;
        $normalizedRunContext = $this->normalizeRunContext($runContext);

        if ($item->post_id) {
            $this->markPublishedLinkedItem($item, 'already_linked_post', $publishPayload['used_translation'], $normalizedRunContext);
            return PublishResult::skipped('already_linked_post');
        }

        if ($publishStatus === BotPublishStatus::SKIPPED->value) {
            $reason = trim((string) data_get($item->meta, 'skip_reason', ''));
            $skipReason = $reason !== '' ? $reason : 'already_skipped';
            $this->markSkipped($item, $skipReason, $publishPayload['used_translation'], $normalizedRunContext);

            return PublishResult::skipped($skipReason);
        }

        $skipReason = $this->resolveSkipReason($item, $publishPayload);
        if ($skipReason !== null) {
            $this->markSkipped($item, $skipReason, $publishPayload['used_translation'], $normalizedRunContext);

            return PublishResult::skipped($skipReason);
        }

        $source = $item->source()->firstOrFail();
        $botIdentity = $item->bot_identity?->value ?? (string) $item->bot_identity;
        $sourceName = $this->sourceNameForPost($source->key);
        $sourceUid = $this->sourceUidForPost($source->key, $item->stable_key);
        $postMeta = $this->buildPostMeta($source, $item, $publishPayload, $normalizedRunContext);

        $existingPost = Post::query()
            ->where('source_name', $sourceName)
            ->where('source_uid', $sourceUid)
            ->first();

        if ($existingPost) {
            $item->forceFill([
                'post_id' => $existingPost->id,
                'publish_status' => BotPublishStatus::PUBLISHED->value,
                'meta' => $this->withPublishAudit($item->meta, $existingPost->id, 'already_published_by_source_uid', $publishPayload['used_translation'], $normalizedRunContext),
            ])->save();

            return PublishResult::skipped('already_published_by_source_uid');
        }

        $attachment = null;
        $temporaryAttachmentPath = null;
        if ($this->isStelaIdentity($botIdentity)) {
            $downloaded = $this->downloadStelaImageAttachment($item);
            if (($downloaded['error'] ?? null) !== null) {
                $reason = (string) $downloaded['error'];
                $this->markSkipped($item, $reason, $publishPayload['used_translation'], $normalizedRunContext);
                return PublishResult::skipped($reason);
            }

            $attachment = $downloaded['attachment'];
            $temporaryAttachmentPath = $downloaded['temporary_path'];
        }

        $botUser = $this->ensureBotUser($botIdentity);
        $content = $this->buildPostContent(
            $publishPayload['title'],
            $publishPayload['body'],
            trim((string) ($item->url ?? '')),
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
                    'source_url' => $postMeta['source_url'] ?? $item->url,
                    'source_uid' => $sourceUid,
                    'source_published_at' => $item->published_at,
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
        $postMeta = $this->buildPostMeta($source, $item, $publishPayload, $this->normalizeRunContext($runContext));
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

        $currentMeta = is_array($post->meta) ? $post->meta : [];
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

    private function ensureBotUser(string $botIdentity): User
    {
        $identity = strtolower(trim($botIdentity));
        $profile = $this->botIdentityProfile($identity);
        $targetUsername = $profile['username'];
        $targetName = $profile['display_name'];
        $preferredEmail = sprintf('%s@astrokomunita.local', $targetUsername);
        $legacyEmail = sprintf('%s@astrokomunita.local', $identity);
        $candidateUsernames = array_values(array_unique(array_filter([$targetUsername, $identity])));
        $candidateEmails = array_values(array_unique(array_filter([$preferredEmail, $legacyEmail])));

        $user = User::query()
            ->where('is_bot', true)
            ->where(function ($query) use ($candidateUsernames, $candidateEmails): void {
                $applied = false;
                foreach ($candidateEmails as $email) {
                    if (!$applied) {
                        $query->where('email', $email);
                        $applied = true;
                    } else {
                        $query->orWhere('email', $email);
                    }
                }

                foreach ($candidateUsernames as $username) {
                    if (!$applied) {
                        $query->where('username', $username);
                        $applied = true;
                    } else {
                        $query->orWhere('username', $username);
                    }
                }
            })
            ->orderBy('id')
            ->first();

        if (!$user) {
            $user = User::query()->create([
                'name' => $targetName,
                'username' => $targetUsername,
                'email' => $preferredEmail,
                'bio' => 'Automated bot account',
                'password' => Str::random(40),
                'is_bot' => true,
                'is_active' => true,
            ]);

            return $user;
        }

        $updates = [];
        if ((string) $user->username !== $targetUsername) {
            $updates['username'] = $targetUsername;
        }
        if ((string) $user->name !== $targetName) {
            $updates['name'] = $targetName;
        }
        if (!in_array((string) $user->email, $candidateEmails, true) || trim((string) $user->email) === '') {
            $updates['email'] = $preferredEmail;
        }
        if (!(bool) $user->is_bot) {
            $updates['is_bot'] = true;
        }
        if (!(bool) $user->is_active) {
            $updates['is_active'] = true;
        }
        if (trim((string) $user->bio) === '') {
            $updates['bio'] = 'Automated bot account';
        }

        if ($updates !== []) {
            $user->forceFill($updates)->save();
        }

        return $user->fresh() ?? $user;
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

    /**
     * @return array{
     *   provider:?string,
     *   model:?string,
     *   target_lang:string,
     *   duration_ms:int,
     *   chars:int,
     *   error:?string,
     *   translated_at:?string
     * }
     */
    private function translationAuditForItem(BotItem $item): array
    {
        $itemMeta = is_array($item->meta) ? $item->meta : [];
        $translationMeta = is_array(data_get($itemMeta, 'translation')) ? data_get($itemMeta, 'translation') : [];

        $provider = $this->nullableString($item->translation_provider)
            ?? $this->nullableString(data_get($translationMeta, 'provider'))
            ?? strtolower(trim((string) config('astrobot.translation.primary', config('astrobot.translation_provider', 'libretranslate'))));

        $translatedAt = $item->translated_at?->toIso8601String()
            ?? $this->nullableString(data_get($translationMeta, 'translated_at'));

        return [
            'provider' => $provider !== '' ? $provider : null,
            'model' => $this->nullableString(data_get($translationMeta, 'model')),
            'target_lang' => strtolower(trim((string) data_get($translationMeta, 'target_lang', 'sk'))),
            'duration_ms' => (int) data_get($translationMeta, 'duration_ms', 0),
            'chars' => (int) data_get($translationMeta, 'chars', 0),
            'error' => $this->nullableString($item->translation_error)
                ?? $this->nullableString(data_get($translationMeta, 'error'))
                ?? $this->nullableString(data_get($itemMeta, 'translation_error')),
            'translated_at' => $translatedAt,
        ];
    }

    /**
     * @param array<string,mixed> $postMeta
     */
    private function syncPostTranslationAudit(Post $post, BotItem $item, array $postMeta): void
    {
        $translationStatus = strtolower(trim((string) ($item->translation_status?->value ?? $item->translation_status)));
        $audit = $this->translationAuditForItem($item);

        $post->forceFill([
            'original_title' => $this->nullableString($item->title),
            'original_body' => $this->nullableString($item->content ?: $item->summary),
            'translated_title' => $this->nullableString($item->title_translated),
            'translated_body' => $this->nullableString($item->content_translated),
            'translation_status' => $translationStatus !== '' ? $translationStatus : null,
            'translation_error' => $this->nullableString($audit['error'] ?? null),
            'translated_at' => $item->translated_at ?? $this->parseDateTimeNullable($audit['translated_at'] ?? null),
            'meta' => $postMeta,
        ])->save();
    }

    /**
     * @return array{username:string,display_name:string}
     */
    private function botIdentityProfile(string $identity): array
    {
        $normalizedIdentity = strtolower(trim($identity));
        $defaults = match ($normalizedIdentity) {
            PostBotIdentity::STELA->value => [
                'username' => 'stellarbot',
                'display_name' => 'Stela',
            ],
            default => [
                'username' => 'kozmobot',
                'display_name' => 'Kozmo',
            ],
        };

        $configuredUsername = strtolower(trim((string) config("astrobot.identities.{$normalizedIdentity}.username", $defaults['username'])));
        $configuredDisplayName = trim((string) config("astrobot.identities.{$normalizedIdentity}.display_name", $defaults['display_name']));

        return [
            'username' => $configuredUsername !== '' ? $configuredUsername : $defaults['username'],
            'display_name' => $configuredDisplayName !== '' ? $configuredDisplayName : $defaults['display_name'],
        ];
    }

    private function sourceLabelForPostMeta(string $sourceKey): string
    {
        $configured = $this->sourceConfigValue($sourceKey, 'label');
        if ($configured !== null) {
            return $configured;
        }

        return match ($sourceKey) {
            'nasa_rss_breaking' => 'NASA RSS',
            'nasa_apod_daily' => 'NASA APOD',
            'wiki_onthisday_astronomy' => 'Wikipedia On This Day',
            default => 'Bot',
        };
    }

    private function sourceAttributionForPostMeta(string $sourceKey): string
    {
        $configured = $this->sourceConfigValue($sourceKey, 'attribution');
        if ($configured !== null) {
            return $configured;
        }

        return match ($sourceKey) {
            'nasa_rss_breaking' => 'NASA',
            'nasa_apod_daily' => 'NASA',
            'wiki_onthisday_astronomy' => 'Wikipedia',
            default => $this->sourceLabelForPostMeta($sourceKey),
        };
    }

    private function sourceConfigValue(string $sourceKey, string $field): ?string
    {
        $value = trim((string) config(sprintf('astrobot.sources.%s.%s', $sourceKey, $field), ''));

        return $value !== '' ? $value : null;
    }

    private function canonicalSourceUrl(BotSource $source, BotItem $item): ?string
    {
        $itemUrl = $this->nullableString($item->url);
        if ($itemUrl !== null) {
            return $itemUrl;
        }

        return $this->nullableString($source->url);
    }

    /**
     * @param mixed $meta
     * @return array<string, mixed>
     */
    private function withPublishAudit(mixed $meta, int $postId, ?string $skipReason, bool $usedTranslation, string $runContext): array
    {
        $payload = is_array($meta) ? $meta : [];
        $payload['published_to_posts_at'] = now()->toIso8601String();
        $payload['post_id'] = $postId;
        $payload['used_translation'] = $usedTranslation;
        $payload['run_context'] = $runContext;
        if ($skipReason !== null) {
            $payload['skip_reason'] = $skipReason;
        } else {
            unset($payload['skip_reason']);
        }

        return $payload;
    }

    /**
     * @param array{title:string,body:string,used_translation:bool} $publishPayload
     */
    private function resolveSkipReason(BotItem $item, array $publishPayload): ?string
    {
        $botIdentity = $item->bot_identity?->value ?? (string) $item->bot_identity;
        if ($this->isStelaIdentity($botIdentity)) {
            $mediaType = strtolower(trim((string) data_get($item->meta, 'media_type', '')));
            if ($mediaType !== '' && $mediaType !== 'image') {
                return 'non_image_media';
            }

            if ($this->resolveStelaImageUrl($item) === '') {
                return 'missing_image_url';
            }
        }

        $title = trim($publishPayload['title']);
        $url = trim((string) ($item->url ?? ''));
        if ($title === '' || $url === '') {
            return 'missing_title_or_url';
        }

        $body = trim($publishPayload['body']);
        $normalizedBody = preg_replace('/\s+/u', ' ', strip_tags($body)) ?? '';
        $normalizedBody = trim($normalizedBody);

        if ($this->stringLength($normalizedBody) < 20) {
            return 'insufficient_content';
        }

        return null;
    }

    private function markSkipped(BotItem $item, string $reason, bool $usedTranslation, string $runContext): void
    {
        $meta = is_array($item->meta) ? $item->meta : [];
        $meta['skip_reason'] = $reason;
        $meta['used_translation'] = $usedTranslation;
        $meta['run_context'] = $runContext;

        $item->forceFill([
            'publish_status' => BotPublishStatus::SKIPPED->value,
            'meta' => $meta,
        ])->save();
    }

    private function markPublishedLinkedItem(BotItem $item, string $reason, bool $usedTranslation, string $runContext): void
    {
        $meta = is_array($item->meta) ? $item->meta : [];
        $meta['skip_reason'] = $reason;
        $meta['used_translation'] = $usedTranslation;
        $meta['run_context'] = $runContext;

        $item->forceFill([
            'publish_status' => BotPublishStatus::PUBLISHED->value,
            'meta' => $meta,
        ])->save();
    }

    /**
     * @return array{
     *   attachment:UploadedFile|null,
     *   temporary_path:?string,
     *   error:?string
     * }
     */
    private function downloadStelaImageAttachment(BotItem $item): array
    {
        $imageUrl = $this->resolveStelaImageUrl($item);
        if ($imageUrl === '') {
            return $this->downloadFailure('image_download_failed');
        }

        if (!$this->isAllowedDownloadScheme($imageUrl)) {
            return $this->downloadFailure('image_policy_violation');
        }

        $timeoutSeconds = max(1, (int) config('astrobot.rss_timeout_seconds', 10));
        $retryTimes = max(0, (int) config('astrobot.rss_retry_times', 2));
        $retrySleepMs = max(0, (int) config('astrobot.rss_retry_sleep_ms', 250));
        $attempts = $retryTimes + 1;
        $maxBytes = max(1024, (int) config('astrobot.stela_image_max_bytes', 20 * 1024 * 1024));

        try {
            $response = Http::secure()
                ->accept('image/*,*/*;q=0.8')
                ->timeout($timeoutSeconds)
                ->withOptions([
                    'allow_redirects' => [
                        'max' => 3,
                        'strict' => true,
                        'protocols' => ['http', 'https'],
                    ],
                ])
                ->retry($attempts, $retrySleepMs, null, false)
                ->get($imageUrl);
        } catch (\Throwable) {
            return $this->downloadFailure('image_download_failed');
        }

        if (!$response->successful()) {
            return $this->downloadFailure('image_download_failed');
        }

        $contentLengthHeader = (int) ($response->header('Content-Length') ?? 0);
        if ($contentLengthHeader > 0 && $contentLengthHeader > $maxBytes) {
            return $this->downloadFailure('image_policy_violation');
        }

        $body = (string) $response->body();
        if ($body === '') {
            return $this->downloadFailure('image_download_failed');
        }

        if (strlen($body) > $maxBytes) {
            return $this->downloadFailure('image_policy_violation');
        }

        $contentType = (string) ($response->header('Content-Type') ?? '');
        $mime = $this->detectImageMime($contentType, $body);
        if ($mime === null || !$this->isAllowedStelaImageMime($mime)) {
            return $this->downloadFailure('image_policy_violation');
        }

        $extension = $this->extensionForImageMime($mime);
        $tempPath = tempnam(sys_get_temp_dir(), 'apod_');
        if ($tempPath === false) {
            return $this->downloadFailure('image_download_failed');
        }

        if ($extension !== 'tmp') {
            $targetPath = $tempPath . '.' . $extension;
            if (@rename($tempPath, $targetPath)) {
                $tempPath = $targetPath;
            }
        }

        $written = @file_put_contents($tempPath, $body);
        if (!is_int($written) || $written <= 0) {
            $this->cleanupTemporaryFile($tempPath);
            return $this->downloadFailure('image_download_failed');
        }

        $datePart = preg_replace('/[^0-9]/', '', (string) data_get($item->meta, 'apod_date', '')) ?? '';
        $basename = $datePart !== '' ? ('apod-' . $datePart) : ('apod-' . sha1($item->stable_key));
        $filename = $basename . '.' . $extension;

        return [
            'attachment' => new UploadedFile($tempPath, $filename, $mime, UPLOAD_ERR_OK, true),
            'temporary_path' => $tempPath,
            'error' => null,
        ];
    }

    private function resolveStelaImageUrl(BotItem $item): string
    {
        $hdurl = trim((string) data_get($item->meta, 'hdurl', ''));
        if ($hdurl !== '') {
            return $hdurl;
        }

        $imageUrl = trim((string) data_get($item->meta, 'image_url', ''));
        if ($imageUrl !== '') {
            return $imageUrl;
        }

        return trim((string) ($item->url ?? ''));
    }

    private function isAllowedStelaImageMime(string $mime): bool
    {
        $normalized = strtolower(trim($mime));
        if ($normalized === '' || !str_starts_with($normalized, 'image/')) {
            return false;
        }

        $allowed = array_values(array_filter(array_map(
            static fn (mixed $value): string => strtolower(trim((string) $value)),
            (array) config('astrobot.stela_image_allowed_mimes', ['image/jpeg', 'image/png', 'image/webp'])
        )));

        return in_array($normalized, $allowed, true);
    }

    private function detectImageMime(string $contentType, string $body): ?string
    {
        $typeHeader = strtolower(trim(explode(';', $contentType)[0] ?? ''));
        if (str_starts_with($typeHeader, 'image/')) {
            return $typeHeader;
        }

        $info = @getimagesizefromstring($body);
        if (is_array($info)) {
            $mime = strtolower(trim((string) ($info['mime'] ?? '')));
            if (str_starts_with($mime, 'image/')) {
                return $mime;
            }
        }

        return null;
    }

    private function extensionForImageMime(string $mime): string
    {
        $normalized = strtolower(trim($mime));

        return match ($normalized) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'tmp',
        };
    }

    private function isAllowedDownloadScheme(string $url): bool
    {
        $scheme = strtolower(trim((string) parse_url($url, PHP_URL_SCHEME)));
        return in_array($scheme, ['http', 'https'], true);
    }

    /**
     * @return array{attachment:null,temporary_path:null,error:string}
     */
    private function downloadFailure(string $reason): array
    {
        return [
            'attachment' => null,
            'temporary_path' => null,
            'error' => $reason,
        ];
    }

    private function isStelaIdentity(string $botIdentity): bool
    {
        return strtolower(trim($botIdentity)) === PostBotIdentity::STELA->value;
    }

    private function normalizeRunContext(string $runContext): string
    {
        $normalized = strtolower(trim($runContext));

        if (in_array($normalized, ['manual', 'scheduled', 'cli', 'admin'], true)) {
            return $normalized;
        }

        return 'manual';
    }

    private function nullableString(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    private function shortHash(string $value): ?string
    {
        $normalized = trim($value);
        if ($normalized === '') {
            return null;
        }

        return substr(sha1($normalized), 0, 8);
    }

    private function parseDateTimeNullable(mixed $value): ?\Carbon\Carbon
    {
        if ($value instanceof \Carbon\Carbon) {
            return $value;
        }

        $normalized = trim((string) $value);
        if ($normalized === '') {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($normalized);
        } catch (\Throwable) {
            return null;
        }
    }

    private function cleanupTemporaryFile(?string $path): void
    {
        if (!is_string($path) || $path === '') {
            return;
        }

        if (is_file($path)) {
            @unlink($path);
        }
    }

    private function stringLength(string $value): int
    {
        if (function_exists('mb_strlen')) {
            return mb_strlen($value);
        }

        return strlen($value);
    }

    private function limitText(string $value, int $maxLength): string
    {
        if ($maxLength <= 0) {
            return '';
        }

        $normalized = preg_replace('/\s+/u', ' ', trim($value)) ?? '';
        if ($normalized === '') {
            return '';
        }

        if ($this->stringLength($normalized) <= $maxLength) {
            return $normalized;
        }

        if ($maxLength <= 3) {
            if (function_exists('mb_substr')) {
                return mb_substr($normalized, 0, $maxLength);
            }

            return substr($normalized, 0, $maxLength);
        }

        if (function_exists('mb_substr')) {
            return mb_substr($normalized, 0, $maxLength - 3) . '...';
        }

        return substr($normalized, 0, $maxLength - 3) . '...';
    }

    /**
     * @return array{title:string,body:string,used_translation:bool}
     */
    private function resolvePublishPayload(BotItem $item): array
    {
        $translationStatus = $item->translation_status?->value ?? (string) $item->translation_status;
        $translationDone = $translationStatus === 'done';

        $titleOriginal = trim((string) $item->title);
        $bodyOriginal = trim((string) ($item->content ?: $item->summary ?: ''));
        $titleTranslated = trim((string) $item->title_translated);
        $bodyTranslated = trim((string) $item->content_translated);

        $useTranslatedTitle = $translationDone && $titleTranslated !== '';
        $useTranslatedBody = $translationDone && $bodyTranslated !== '';

        return [
            'title' => $useTranslatedTitle ? $titleTranslated : $titleOriginal,
            'body' => $useTranslatedBody ? $bodyTranslated : $bodyOriginal,
            'used_translation' => $useTranslatedTitle || $useTranslatedBody,
        ];
    }
}
