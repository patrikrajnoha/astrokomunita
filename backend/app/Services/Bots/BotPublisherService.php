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
use Illuminate\Support\Str;

class BotPublisherService
{
    public function __construct(
        private readonly PostService $postService,
    ) {
    }

    public function publishItemToAstroFeed(BotItem $item): PublishResult
    {
        $publishPayload = $this->resolvePublishPayload($item);
        $publishStatus = $item->publish_status?->value ?? (string) $item->publish_status;

        if ($item->post_id) {
            $this->markPublishedLinkedItem($item, 'already_linked_post', $publishPayload['used_translation']);
            return PublishResult::skipped('already_linked_post');
        }

        if ($publishStatus === BotPublishStatus::SKIPPED->value) {
            $reason = trim((string) data_get($item->meta, 'skip_reason', ''));
            $skipReason = $reason !== '' ? $reason : 'already_skipped';
            $this->markSkipped($item, $skipReason, $publishPayload['used_translation']);

            return PublishResult::skipped($skipReason);
        }

        $skipReason = $this->resolveSkipReason($item, $publishPayload);
        if ($skipReason !== null) {
            $this->markSkipped($item, $skipReason, $publishPayload['used_translation']);

            return PublishResult::skipped($skipReason);
        }

        $source = $item->source()->firstOrFail();
        $botIdentity = $item->bot_identity?->value ?? (string) $item->bot_identity;
        $sourceName = $this->sourceNameForPost($source->key);
        $sourceUid = $this->sourceUidForPost($source->key, $item->stable_key);
        $postMeta = $this->buildPostMeta($source, $item, $publishPayload);

        $existingPost = Post::query()
            ->where('source_name', $sourceName)
            ->where('source_uid', $sourceUid)
            ->first();

        if ($existingPost) {
            $item->forceFill([
                'post_id' => $existingPost->id,
                'publish_status' => BotPublishStatus::PUBLISHED->value,
                'meta' => $this->withPublishAudit($item->meta, $existingPost->id, 'already_published_by_source_uid', $publishPayload['used_translation']),
            ])->save();

            return PublishResult::skipped('already_published_by_source_uid');
        }

        $attachment = null;
        $temporaryAttachmentPath = null;
        if ($this->isStelaIdentity($botIdentity)) {
            $downloaded = $this->downloadStelaImageAttachment($item);
            if ($downloaded === null) {
                $this->markSkipped($item, 'image_download_failed', $publishPayload['used_translation']);
                return PublishResult::skipped('image_download_failed');
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
            $item
        );

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

        $item->forceFill([
            'post_id' => $post->id,
            'publish_status' => BotPublishStatus::PUBLISHED->value,
            'meta' => $this->withPublishAudit($item->meta, $post->id, null, $publishPayload['used_translation']),
        ])->save();

        return PublishResult::published($post);
    }

    private function ensureBotUser(string $botIdentity): User
    {
        $identity = strtolower(trim($botIdentity));
        $email = sprintf('%s@astrokomunita.local', $identity);

        return User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => Str::title($identity),
                'username' => $identity,
                'bio' => 'Automated bot account',
                'password' => Str::random(40),
                'is_bot' => true,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
    }

    private function buildPostContent(string $headline, string $body, string $url, string $botIdentity, BotItem $item): string
    {
        if ($this->isStelaIdentity($botIdentity)) {
            return $this->buildStelaPostContent($headline, $body, $url, $item);
        }

        return $this->buildKozmoPostContent($headline, $body, $url);
    }

    private function buildKozmoPostContent(string $headline, string $body, string $url): string
    {
        if ($headline === '') {
            $headline = 'NASA update';
        }

        $lines = [
            sprintf('NASA | %s', $headline),
        ];

        if ($body !== '') {
            $lines[] = '';
            $lines[] = $body;
        }

        $lines[] = '';
        $lines[] = 'Source: NASA';

        if ($url !== '') {
            $lines[] = $url;
        }

        return implode("\n", $lines);
    }

    private function buildStelaPostContent(string $headline, string $body, string $url, BotItem $item): string
    {
        $title = trim($headline);
        if ($title === '') {
            $title = 'NASA APOD';
        }
        $title = $this->limitText($title, 300);

        $apodDate = trim((string) data_get($item->meta, 'apod_date', ''));
        $copyright = trim((string) data_get($item->meta, 'copyright', ''));

        $attributionParts = ['NASA APOD'];
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
    private function buildPostMeta(BotSource $source, BotItem $item, array $publishPayload): array
    {
        $botIdentity = strtolower(trim((string) ($item->bot_identity?->value ?? $item->bot_identity)));
        $sourceKey = strtolower(trim((string) $source->key));
        $translationStatus = strtolower(trim((string) ($item->translation_status?->value ?? $item->translation_status)));
        $itemMeta = is_array($item->meta) ? $item->meta : [];
        $translationProvider = trim((string) data_get($itemMeta, 'translation.provider', ''));
        if ($translationProvider === '') {
            $translationProvider = strtolower(trim((string) config('astrobot.translation_provider', 'dummy')));
        }

        return [
            'bot_identity' => $botIdentity !== '' ? $botIdentity : null,
            'bot_source_key' => $sourceKey !== '' ? $sourceKey : null,
            'bot_source_label' => $this->sourceLabelForPostMeta($sourceKey),
            'source_url' => $this->canonicalSourceUrl($source, $item),
            'published_by' => 'bot-engine',
            'published_at_utc' => now()->utc()->toIso8601String(),
            'original_title' => $this->nullableString($item->title),
            'original_content' => $this->nullableString($item->content ?: $item->summary),
            'translated_title' => $this->nullableString($item->title_translated),
            'translated_content' => $this->nullableString($item->content_translated),
            'translation_status' => $translationStatus !== '' ? $translationStatus : null,
            'used_translation' => (bool) ($publishPayload['used_translation'] ?? false),
            'translation_provider' => $translationProvider !== '' ? $translationProvider : null,
        ];
    }

    private function sourceLabelForPostMeta(string $sourceKey): string
    {
        return match ($sourceKey) {
            'nasa_rss_breaking' => 'NASA RSS',
            'nasa_apod_daily' => 'NASA APOD',
            'wiki_onthisday_astronomy' => 'Wikipedia On This Day',
            default => 'Bot',
        };
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
    private function withPublishAudit(mixed $meta, int $postId, ?string $skipReason, bool $usedTranslation): array
    {
        $payload = is_array($meta) ? $meta : [];
        $payload['published_to_posts_at'] = now()->toIso8601String();
        $payload['post_id'] = $postId;
        $payload['used_translation'] = $usedTranslation;
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

    private function markSkipped(BotItem $item, string $reason, bool $usedTranslation): void
    {
        $meta = is_array($item->meta) ? $item->meta : [];
        $meta['skip_reason'] = $reason;
        $meta['used_translation'] = $usedTranslation;

        $item->forceFill([
            'publish_status' => BotPublishStatus::SKIPPED->value,
            'meta' => $meta,
        ])->save();
    }

    private function markPublishedLinkedItem(BotItem $item, string $reason, bool $usedTranslation): void
    {
        $meta = is_array($item->meta) ? $item->meta : [];
        $meta['skip_reason'] = $reason;
        $meta['used_translation'] = $usedTranslation;

        $item->forceFill([
            'publish_status' => BotPublishStatus::PUBLISHED->value,
            'meta' => $meta,
        ])->save();
    }

    /**
     * @return array{attachment:UploadedFile,temporary_path:string}|null
     */
    private function downloadStelaImageAttachment(BotItem $item): ?array
    {
        $imageUrl = $this->resolveStelaImageUrl($item);
        if ($imageUrl === '') {
            return null;
        }

        $timeoutSeconds = max(1, (int) config('astrobot.rss_timeout_seconds', 10));
        $retryTimes = max(0, (int) config('astrobot.rss_retry_times', 2));
        $retrySleepMs = max(0, (int) config('astrobot.rss_retry_sleep_ms', 250));
        $attempts = $retryTimes + 1;

        try {
            $response = Http::secure()
                ->accept('image/*,*/*;q=0.8')
                ->timeout($timeoutSeconds)
                ->retry($attempts, $retrySleepMs, null, false)
                ->get($imageUrl);
        } catch (\Throwable) {
            return null;
        }

        if (!$response->successful()) {
            return null;
        }

        $body = (string) $response->body();
        if ($body === '') {
            return null;
        }

        $contentType = (string) ($response->header('Content-Type') ?? '');
        $mime = $this->detectImageMime($contentType, $body);
        if ($mime === null || !$this->isAllowedStelaImageMime($mime)) {
            return null;
        }

        $extension = $this->extensionForImageMime($mime);
        $tempPath = tempnam(sys_get_temp_dir(), 'apod_');
        if ($tempPath === false) {
            return null;
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
            return null;
        }

        $datePart = preg_replace('/[^0-9]/', '', (string) data_get($item->meta, 'apod_date', '')) ?? '';
        $basename = $datePart !== '' ? ('apod-' . $datePart) : ('apod-' . sha1($item->stable_key));
        $filename = $basename . '.' . $extension;

        return [
            'attachment' => new UploadedFile($tempPath, $filename, $mime, UPLOAD_ERR_OK, true),
            'temporary_path' => $tempPath,
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
            (array) config('media.post_image_allowed_mimes', [])
        )));

        if ($allowed === []) {
            return true;
        }

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
            'image/gif' => 'gif',
            default => 'tmp',
        };
    }

    private function isStelaIdentity(string $botIdentity): bool
    {
        return strtolower(trim($botIdentity)) === PostBotIdentity::STELA->value;
    }

    private function nullableString(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
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
