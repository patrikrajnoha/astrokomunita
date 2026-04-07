<?php

namespace App\Services\Bots\Concerns;

use App\Enums\BotPublishStatus;
use App\Enums\PostAuthorKind;
use App\Enums\PostBotIdentity;
use App\Enums\PostFeedKey;
use App\Models\BotItem;
use App\Models\BotSource;
use App\Models\Post;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

trait ManagesBotPublisherInternals
{
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
            ?? strtolower(trim((string) config('bots.translation.primary', 'libretranslate')));

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
                'display_name' => 'Stella',
            ],
            default => [
                'username' => 'kozmobot',
                'display_name' => 'Kozmo',
            ],
        };

        $configuredUsername = strtolower(trim((string) config("bots.identities.{$normalizedIdentity}.username", $defaults['username'])));
        $configuredDisplayName = trim((string) config("bots.identities.{$normalizedIdentity}.display_name", $defaults['display_name']));

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
        $value = trim((string) config(sprintf('bots.sources.%s.%s', $sourceKey, $field), ''));

        return $value !== '' ? $value : null;
    }

    private function canonicalSourceUrl(BotSource $source, BotItem $item): ?string
    {
        $itemUrl = $this->nullableString($item->url);
        if ($itemUrl !== null) {
            return $itemUrl;
        }

        $fallbackUrl = $this->nullableString($this->canonicalItemUrlForPublish($item));
        if ($fallbackUrl !== null) {
            return $fallbackUrl;
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
    private function resolveSkipReason(BotItem $item, array $publishPayload, string $resolvedSourceUrl): ?string
    {
        $botIdentity = $item->bot_identity?->value ?? (string) $item->bot_identity;
        if ($this->isStelaIdentity($botIdentity)) {
            $mediaType = $this->stelaMediaType($item);
            if ($mediaType === 'image' && $this->resolveStelaAttachmentUrl($item) === '') {
                return 'missing_image_url';
            }

            if ($mediaType === 'video' && trim($resolvedSourceUrl) === '') {
                return 'missing_video_url';
            }
        }

        $title = trim($publishPayload['title']);
        $url = trim($resolvedSourceUrl);
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

    /**
     * @param array<string,mixed> $extraMeta
     */
    private function markSkipped(
        BotItem $item,
        string $reason,
        bool $usedTranslation,
        string $runContext,
        array $extraMeta = []
    ): void
    {
        $meta = is_array($item->meta) ? $item->meta : [];
        $meta['skip_reason'] = $reason;
        $meta['used_translation'] = $usedTranslation;
        $meta['run_context'] = $runContext;
        foreach ($extraMeta as $key => $value) {
            $meta[$key] = $value;
        }

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

        $postId = (int) ($item->post_id ?? 0);
        if ($postId > 0) {
            $linkedPost = Post::query()->find($postId);
            if ($linkedPost && (!$linkedPost->bot_item_id || !$linkedPost->ingested_at)) {
                $linkedPost->forceFill([
                    'bot_item_id' => $item->id,
                    'ingested_at' => $linkedPost->ingested_at ?: now(),
                ])->save();
            }
        }

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
    private function downloadStelaAttachment(BotItem $item): array
    {
        $mediaType = $this->stelaMediaType($item);
        $downloadFailureReason = $mediaType === 'video' ? 'video_download_failed' : 'image_download_failed';
        $policyFailureReason = $mediaType === 'video' ? 'video_policy_violation' : 'image_policy_violation';

        $attachmentUrl = $this->resolveStelaAttachmentUrl($item);
        if ($attachmentUrl === '') {
            return $this->downloadFailure($downloadFailureReason);
        }

        if (!$this->isAllowedDownloadScheme($attachmentUrl)) {
            return $this->downloadFailure($policyFailureReason);
        }

        $timeoutSeconds = max(1, (int) config('bots.rss_timeout_seconds', 10));
        $retryTimes = max(0, (int) config('bots.rss_retry_times', 2));
        $retrySleepMs = max(0, (int) config('bots.rss_retry_sleep_ms', 250));
        $attempts = $retryTimes + 1;
        $maxBytes = max(1024, (int) config('bots.stela_attachment_max_bytes', (int) config('bots.stela_image_max_bytes', 20 * 1024 * 1024)));

        try {
            $response = Http::secure()
                ->accept('image/*,video/mp4,*/*;q=0.8')
                ->timeout($timeoutSeconds)
                ->withOptions([
                    'allow_redirects' => [
                        'max' => 3,
                        'strict' => true,
                        'protocols' => ['http', 'https'],
                    ],
                ])
                ->retry($attempts, $retrySleepMs, null, false)
                ->get($attachmentUrl);
        } catch (\Throwable) {
            return $this->downloadFailure($downloadFailureReason);
        }

        if (!$response->successful()) {
            return $this->downloadFailure($downloadFailureReason);
        }

        $contentLengthHeader = (int) ($response->header('Content-Length') ?? 0);
        if ($contentLengthHeader > 0 && $contentLengthHeader > $maxBytes) {
            return $this->downloadFailure($policyFailureReason);
        }

        $body = (string) $response->body();
        if ($body === '') {
            return $this->downloadFailure($downloadFailureReason);
        }

        if (strlen($body) > $maxBytes) {
            return $this->downloadFailure($policyFailureReason);
        }

        $contentType = (string) ($response->header('Content-Type') ?? '');
        $mime = $this->detectStelaAttachmentMime($contentType, $body, $attachmentUrl);
        if ($mime === null || !$this->isAllowedStelaAttachmentMime($mime)) {
            return $this->downloadFailure($policyFailureReason);
        }

        $extension = $this->extensionForStelaAttachmentMime($mime);
        $tempPath = tempnam(sys_get_temp_dir(), 'apod_');
        if ($tempPath === false) {
            return $this->downloadFailure($downloadFailureReason);
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
            return $this->downloadFailure($downloadFailureReason);
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

    private function resolveStelaAttachmentUrl(BotItem $item): string
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

    private function shouldDownloadStelaAttachment(BotItem $item): bool
    {
        $mediaType = $this->stelaMediaType($item);
        $attachmentUrl = $this->resolveStelaAttachmentUrl($item);
        if ($attachmentUrl === '') {
            return false;
        }

        $path = strtolower(trim((string) parse_url($attachmentUrl, PHP_URL_PATH)));
        $extension = strtolower(trim((string) pathinfo($path, PATHINFO_EXTENSION)));

        if ($mediaType === 'video') {
            return $extension === 'mp4';
        }

        if ($mediaType !== '' && $mediaType !== 'image') {
            return false;
        }

        if (in_array($extension, ['mov', 'webm', 'm3u8'], true)) {
            return false;
        }

        return true;
    }

    private function isRetryableSkippedReason(string $reason): bool
    {
        $normalized = strtolower(trim($reason));

        if ($normalized === '') {
            return false;
        }

        return in_array($normalized, [
            'non_image_media',
            'image_download_failed',
            'missing_image_url',
            'video_download_failed',
            'missing_video_url',
            'missing_title_or_url',
            'publish_rate_limited',
        ], true);
    }

    private function stelaMediaType(BotItem $item): string
    {
        $mediaType = strtolower(trim((string) data_get($item->meta, 'media_type', '')));

        if ($mediaType === '') {
            return 'image';
        }

        return $mediaType;
    }

    private function isAllowedStelaAttachmentMime(string $mime): bool
    {
        $normalized = strtolower(trim($mime));
        if ($normalized === '') {
            return false;
        }

        $allowedImageMimes = array_values(array_filter(array_map(
            static fn (mixed $value): string => strtolower(trim((string) $value)),
            (array) config('bots.stela_image_allowed_mimes', ['image/jpeg', 'image/png', 'image/webp'])
        )));
        if (in_array($normalized, $allowedImageMimes, true)) {
            return true;
        }

        $allowedVideoMimes = array_values(array_filter(array_map(
            static fn (mixed $value): string => strtolower(trim((string) $value)),
            (array) config('bots.stela_video_allowed_mimes', ['video/mp4'])
        )));

        return in_array($normalized, $allowedVideoMimes, true);
    }

    private function detectStelaAttachmentMime(string $contentType, string $body, string $sourceUrl): ?string
    {
        $typeHeader = strtolower(trim(explode(';', $contentType)[0] ?? ''));
        if (str_starts_with($typeHeader, 'image/') || $typeHeader === 'video/mp4') {
            return $typeHeader;
        }

        $info = @getimagesizefromstring($body);
        if (is_array($info)) {
            $mime = strtolower(trim((string) ($info['mime'] ?? '')));
            if (str_starts_with($mime, 'image/')) {
                return $mime;
            }
        }

        $path = strtolower(trim((string) parse_url($sourceUrl, PHP_URL_PATH)));
        $extension = strtolower(trim((string) pathinfo($path, PATHINFO_EXTENSION)));
        if ($extension === 'mp4') {
            return 'video/mp4';
        }

        return null;
    }

    private function extensionForStelaAttachmentMime(string $mime): string
    {
        $normalized = strtolower(trim($mime));

        return match ($normalized) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'video/mp4' => 'mp4',
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

    /**
     * @param array{title:string,body:string,used_translation:bool} $publishPayload
     */
    private function repairRecoverablePublishFields(
        BotItem $item,
        array $publishPayload,
        string $resolvedSourceUrl,
        string $runContext
    ): void {
        $title = trim((string) ($publishPayload['title'] ?? ''));
        $url = trim($resolvedSourceUrl);

        $updates = [];
        if (trim((string) ($item->title ?? '')) === '' && $title !== '') {
            $updates['title'] = $title;
        }
        if (trim((string) ($item->url ?? '')) === '' && $url !== '') {
            $updates['url'] = $url;
        }

        if ($updates === []) {
            return;
        }

        $meta = is_array($item->meta) ? $item->meta : [];
        $meta['field_recovery'] = [
            'applied_at' => now()->toIso8601String(),
            'run_context' => $runContext,
            'title_recovered' => array_key_exists('title', $updates),
            'url_recovered' => array_key_exists('url', $updates),
        ];

        $item->forceFill(array_replace($updates, ['meta' => $meta]))->save();
    }

    private function canonicalItemUrlForPublish(BotItem $item): string
    {
        $itemUrl = trim((string) ($item->url ?? ''));
        if ($itemUrl !== '') {
            return $itemUrl;
        }

        $botIdentity = $item->bot_identity?->value ?? (string) $item->bot_identity;
        if (!$this->isStelaIdentity($botIdentity)) {
            return '';
        }

        $attachmentUrl = trim($this->resolveStelaAttachmentUrl($item));
        if ($attachmentUrl !== '') {
            return $attachmentUrl;
        }

        $apodDate = trim((string) data_get($item->meta, 'apod_date', ''));
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $apodDate, $matches) === 1) {
            $year = (int) $matches[1];
            $month = (int) $matches[2];
            $day = (int) $matches[3];

            return sprintf('https://apod.nasa.gov/apod/ap%02d%02d%02d.html', $year % 100, $month, $day);
        }

        return '';
    }

    /**
     * @param array<string,mixed> $meta
     */
    private function logPublishActivity(
        BotItem $item,
        string $outcome,
        ?string $reason,
        string $runContext,
        ?int $postId = null,
        array $meta = []
    ): void {
        $this->activityLogService->record(
            action: 'publish',
            outcome: $outcome,
            item: $item,
            source: null,
            run: null,
            postId: $postId,
            reason: $reason,
            runContext: $runContext,
            message: null,
            meta: $meta
        );
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
        $resolvedTitle = $useTranslatedTitle ? $titleTranslated : $titleOriginal;
        if ($resolvedTitle === '') {
            $botIdentity = $item->bot_identity?->value ?? (string) $item->bot_identity;
            if ($this->isStelaIdentity($botIdentity)) {
                $apodDate = trim((string) data_get($item->meta, 'apod_date', ''));
                $resolvedTitle = $apodDate !== '' ? sprintf('NASA APOD %s', $apodDate) : 'NASA APOD';
            }
        }

        return [
            'title' => $resolvedTitle,
            'body' => $useTranslatedBody ? $bodyTranslated : $bodyOriginal,
            'used_translation' => $useTranslatedTitle || $useTranslatedBody,
        ];
    }
}
