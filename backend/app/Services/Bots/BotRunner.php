<?php

namespace App\Services\Bots;

use App\Enums\BotSourceType;
use App\Enums\BotPublishStatus;
use App\Enums\BotRunStatus;
use App\Enums\BotTranslationStatus;
use App\Models\BotItem;
use App\Models\BotRun;
use App\Models\BotSource;
use App\Services\Bots\Contracts\BotTranslationServiceInterface;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Collection;
use Throwable;

class BotRunner
{
    private const MODE_AUTO = 'auto';
    private const MODE_DRY = 'dry';

    public function __construct(
        private readonly BotRunService $runService,
        private readonly RssFetchService $rssFetchService,
        private readonly NasaApodFetchService $nasaApodFetchService,
        private readonly WikipediaOnThisDayFetchService $wikipediaOnThisDayFetchService,
        private readonly BotItemDedupeService $dedupeService,
        private readonly BotPublisherService $publisherService,
        private readonly BotTranslationServiceInterface $translationService,
    ) {
    }

    public function runSource(
        string $sourceKey,
        string $runContext = 'manual',
        bool $forceManualOverride = false,
        string $mode = self::MODE_AUTO,
        ?int $publishLimit = null
    ): BotRun
    {
        $source = BotSource::query()->where('key', $sourceKey)->firstOrFail();

        return $this->run($source, $runContext, $forceManualOverride, $mode, $publishLimit);
    }

    public function run(
        BotSource $source,
        string $runContext = 'manual',
        bool $forceManualOverride = false,
        string $mode = self::MODE_AUTO,
        ?int $publishLimit = null
    ): BotRun
    {
        $context = $this->normalizeRunContext($runContext);
        $runMode = $this->normalizeRunMode($mode);
        $normalizedPublishLimit = $this->normalizePublishLimit($publishLimit);
        $runMeta = [
            'run_context' => $context,
            'mode' => $runMode,
            'publish_limit' => $normalizedPublishLimit,
        ];

        $lockState = $this->acquireRunLocks($source, $context, $forceManualOverride);

        if (!$lockState['acquired']) {
            $run = $this->runService->startRun($source, $runMeta);
            $stats = $this->createInitialStats();
            $stats['run_locked'] = 1;
            $stats['lock_key'] = $lockState['lock_key'];
            $stats['lock_context'] = $context;
            $stats['run_context'] = $context;
            $stats['manual_override'] = $forceManualOverride ? 1 : 0;
            $stats['mode'] = $runMode;
            $stats['publish_limit'] = $normalizedPublishLimit;

            return $this->runService->finishRun(
                $run,
                BotRunStatus::SKIPPED,
                $stats,
                'Run skipped because lock is already held.',
                $runMeta
            );
        }

        $run = $this->runService->startRun($source, $runMeta);
        $stats = $this->createInitialStats();
        $stats['lock_key'] = $lockState['lock_key'];
        $stats['lock_context'] = $context;
        $stats['manual_override'] = $forceManualOverride ? 1 : 0;
        $stats['run_context'] = $context;
        $stats['mode'] = $runMode;
        $stats['publish_limit'] = $normalizedPublishLimit;

        $status = BotRunStatus::SUCCESS;
        $errorText = null;

        try {
            $rows = $this->fetchRowsForSource($source);
            $stats['fetched_count'] = count($rows);
            $this->mergeWikidataDiagnostics($source, $stats);
            $items = $this->dedupeRows($source, $rows, $stats, $context, $run->id);
            $this->applyTranslationStep($source, $stats, $context, $run->id);
            if ($runMode === self::MODE_AUTO) {
                $this->publishRows($items, $stats, $context, $normalizedPublishLimit);
            }

            if ($stats['failed_count'] > 0) {
                $status = BotRunStatus::PARTIAL;
            }
        } catch (Throwable $e) {
            $status = BotRunStatus::FAILED;
            $errorText = $e->getMessage();
            $stats['failed_count']++;
            $this->recordErrorFingerprint($stats, $e);
        } finally {
            $this->releaseRunLocks($lockState);
        }

        return $this->runService->finishRun($run, $status, $stats, $errorText, $runMeta);
    }

    /**
     * @return array<string,mixed>
     */
    private function createInitialStats(): array
    {
        return [
            'fetched_count' => 0,
            'new_count' => 0,
            'dupes_count' => 0,
            'published_count' => 0,
            'skipped_count' => 0,
            'failed_count' => 0,
            'translation_done_count' => 0,
            'translation_failed_count' => 0,
            'wikidata_checked_count' => 0,
            'wikidata_cached_hits' => 0,
            'image_skipped_policy_count' => 0,
            'error_fingerprints' => [],
        ];
    }

    /**
     * @return array<int, array{stable_key:string,payload:array<string,mixed>}>
     */
    private function fetchRowsForSource(BotSource $source): array
    {
        $sourceKey = strtolower(trim((string) $source->key));
        $sourceType = $source->source_type?->value ?? (string) $source->source_type;

        if ($sourceKey === 'nasa_rss_breaking') {
            return $this->rssFetchService->fetch($source);
        }

        if ($sourceKey === 'nasa_apod_daily') {
            return $this->nasaApodFetchService->fetch($source);
        }

        if ($sourceKey === 'wiki_onthisday_astronomy') {
            return $this->wikipediaOnThisDayFetchService->fetch($source);
        }

        if ($sourceType === BotSourceType::RSS->value) {
            return $this->rssFetchService->fetch($source);
        }

        if ($sourceType === BotSourceType::WIKIPEDIA->value) {
            return $this->wikipediaOnThisDayFetchService->fetch($source);
        }

        throw new \InvalidArgumentException(sprintf(
            'Unsupported bot source "%s" (type "%s").',
            $sourceKey,
            $sourceType
        ));
    }

    /**
     * @param array<string,mixed> $stats
     */
    private function mergeWikidataDiagnostics(BotSource $source, array &$stats): void
    {
        $sourceKey = strtolower(trim((string) $source->key));
        $sourceType = $source->source_type?->value ?? (string) $source->source_type;
        if ($sourceKey !== 'wiki_onthisday_astronomy' && $sourceType !== BotSourceType::WIKIPEDIA->value) {
            return;
        }

        $diagnostics = $this->wikipediaOnThisDayFetchService->getLastDiagnostics();
        $stats['wikidata_checked_count'] = (int) ($stats['wikidata_checked_count'] ?? 0) + (int) ($diagnostics['wikidata_checked_count'] ?? 0);
        $stats['wikidata_cached_hits'] = (int) ($stats['wikidata_cached_hits'] ?? 0) + (int) ($diagnostics['wikidata_cached_hits'] ?? 0);
    }

    /**
     * @param array<int, array{stable_key:string,payload:array<string,mixed>}> $rows
     * @param array<string,mixed> $stats
     * @return Collection<int, BotItem>
     */
    private function dedupeRows(
        BotSource $source,
        array $rows,
        array &$stats,
        string $runContext,
        int $runId
    ): Collection
    {
        $items = collect();

        foreach ($rows as $row) {
            $item = null;
            try {
                $item = $this->dedupeService->upsertByStableKey(
                    $source,
                    $row['stable_key'],
                    $row['payload'],
                    $runId
                );
                if ($item->wasRecentlyCreated) {
                    $stats['new_count']++;
                } else {
                    $stats['dupes_count']++;
                }

                $this->stampItemRunContext($item, $runContext);
                $items->push($item);
            } catch (Throwable $e) {
                $stats['failed_count']++;
                $this->recordErrorFingerprint($stats, $e);

                if ($item instanceof BotItem) {
                    $meta = is_array($item->meta) ? $item->meta : [];
                    $meta['last_error'] = $this->limitText($e->getMessage(), 300);
                    $meta['run_context'] = $runContext;

                    $item->forceFill([
                        'publish_status' => BotPublishStatus::FAILED->value,
                        'meta' => $meta,
                    ])->save();
                }
            }
        }

        return $items;
    }

    /**
     * @param Collection<int, BotItem> $items
     * @param array<string,mixed> $stats
     */
    private function publishRows(
        Collection $items,
        array &$stats,
        string $runContext,
        ?int $publishLimit = null
    ): void
    {
        $publishedCount = 0;

        foreach ($items as $item) {
            if ($publishLimit !== null && $publishedCount >= $publishLimit) {
                break;
            }

            try {
                $item->refresh();
                $this->stampItemRunContext($item, $runContext);
                $publishResult = $this->publisherService->publishItemToAstroFeed($item, $runContext);

                if ($publishResult->isPublished()) {
                    $stats['published_count']++;
                    $publishedCount++;
                } elseif ($publishResult->isSkipped()) {
                    $stats['skipped_count']++;
                    if ((string) $publishResult->reason === 'image_policy_violation') {
                        $stats['image_skipped_policy_count'] = (int) ($stats['image_skipped_policy_count'] ?? 0) + 1;
                    }
                }
            } catch (Throwable $e) {
                $stats['failed_count']++;
                $this->recordErrorFingerprint($stats, $e);
                $meta = is_array($item->meta) ? $item->meta : [];
                $meta['last_error'] = $this->limitText($e->getMessage(), 300);
                $meta['run_context'] = $runContext;

                $item->forceFill([
                    'publish_status' => BotPublishStatus::FAILED->value,
                    'meta' => $meta,
                ])->save();
            }
        }
    }

    /**
     * @param array<string,mixed> $stats
     */
    private function applyTranslationStep(BotSource $source, array &$stats, string $runContext, int $runId): void
    {
        $items = BotItem::query()
            ->where('source_id', $source->id)
            ->where(function ($query) use ($runId): void {
                $query
                    ->where('run_id', $runId)
                    ->orWhere('meta->last_seen_run_id', $runId);
            })
            ->whereNull('post_id')
            ->where('publish_status', BotPublishStatus::PENDING->value)
            ->get();

        foreach ($items as $item) {
            $this->stampItemRunContext($item, $runContext);
            $currentStatus = $item->translation_status?->value ?? (string) $item->translation_status;
            if (!in_array($currentStatus, ['', BotTranslationStatus::PENDING->value, null], true)) {
                continue;
            }

            $langOriginal = strtolower(trim((string) $item->lang_original));
            if ($langOriginal === 'sk' || str_starts_with($langOriginal, 'sk-')) {
                $meta = is_array($item->meta) ? $item->meta : [];
                $meta['run_context'] = $runContext;
                $item->forceFill([
                    'translation_status' => BotTranslationStatus::SKIPPED->value,
                    'meta' => $meta,
                ])->save();

                continue;
            }

            $title = trim((string) $item->title);
            $content = trim((string) ($item->content ?: $item->summary ?: ''));

            if ($title === '' && $content === '') {
                $meta = is_array($item->meta) ? $item->meta : [];
                $meta['run_context'] = $runContext;
                $item->forceFill([
                    'translation_status' => BotTranslationStatus::SKIPPED->value,
                    'meta' => $meta,
                ])->save();

                continue;
            }

            $meta = is_array($item->meta) ? $item->meta : [];
            $cacheKey = sha1('sk|' . $title . '|' . $content);
            $existingCacheKey = trim((string) ($meta['translation_cache_key'] ?? ''));

            if ($existingCacheKey !== '' && hash_equals($existingCacheKey, $cacheKey)) {
                if ($this->hasAnyTranslatedText($item)) {
                    $meta['run_context'] = $runContext;
                    $item->forceFill([
                        'translation_status' => BotTranslationStatus::DONE->value,
                        'meta' => $meta,
                    ])->save();
                    $stats['translation_done_count'] = (int) ($stats['translation_done_count'] ?? 0) + 1;
                }

                continue;
            }

            try {
                $result = $this->translationService->translate($title, $content, 'sk');
                $meta['translation_cache_key'] = $cacheKey;
                unset($meta['translation_error']);

                $translationMeta = is_array($result['meta'] ?? null) ? $result['meta'] : null;
                if ($translationMeta !== null) {
                    $meta['translation'] = $translationMeta;
                }
                $meta['run_context'] = $runContext;

                $item->forceFill([
                    'title_translated' => $this->nullableString($result['translated_title'] ?? $result['title_translated'] ?? null),
                    'content_translated' => $this->nullableString($result['translated_content'] ?? $result['content_translated'] ?? null),
                    'translation_status' => BotTranslationStatus::DONE->value,
                    'meta' => $meta,
                ])->save();
                $stats['translation_done_count'] = (int) ($stats['translation_done_count'] ?? 0) + 1;
            } catch (Throwable $e) {
                $stats['failed_count']++;
                $stats['translation_failed_count'] = (int) ($stats['translation_failed_count'] ?? 0) + 1;
                $this->recordErrorFingerprint($stats, $e);
                $meta['translation_error'] = $this->limitText($e->getMessage(), 300);
                $meta['run_context'] = $runContext;

                $item->forceFill([
                    'translation_status' => BotTranslationStatus::FAILED->value,
                    'meta' => $meta,
                ])->save();
            }
        }
    }

    private function normalizeRunContext(string $runContext): string
    {
        $normalized = strtolower(trim($runContext));

        if (in_array($normalized, ['manual', 'scheduled', 'cli', 'admin'], true)) {
            return $normalized;
        }

        return 'manual';
    }

    private function normalizeRunMode(string $mode): string
    {
        $normalized = strtolower(trim($mode));

        if ($normalized === self::MODE_DRY) {
            return self::MODE_DRY;
        }

        return self::MODE_AUTO;
    }

    private function normalizePublishLimit(?int $publishLimit): ?int
    {
        if ($publishLimit === null) {
            return null;
        }

        if ($publishLimit < 0) {
            return null;
        }

        return $publishLimit;
    }

    /**
     * @return array{
     *   acquired:bool,
     *   lock_key:string,
     *   locks:array<int,Lock>
     * }
     */
    private function acquireRunLocks(BotSource $source, string $runContext, bool $forceManualOverride): array
    {
        $sourceKey = strtolower(trim((string) $source->key));
        $ttlSeconds = max(60, (int) config('astrobot.run_lock_ttl_seconds', 600));
        $locks = [];

        $contextLockKey = sprintf('bots:run:%s:%s', $runContext, $sourceKey);
        $contextLock = Cache::lock($contextLockKey, $ttlSeconds);
        if (!$contextLock->get()) {
            return [
                'acquired' => false,
                'lock_key' => $contextLockKey,
                'locks' => [],
            ];
        }
        $locks[] = $contextLock;

        $globalLockKey = sprintf('bots:run:%s', $sourceKey);
        if (!($forceManualOverride && in_array($runContext, ['manual', 'admin'], true))) {
            $globalLock = Cache::lock($globalLockKey, $ttlSeconds);
            if (!$globalLock->get()) {
                $this->releaseRunLocks([
                    'acquired' => true,
                    'lock_key' => $contextLockKey,
                    'locks' => $locks,
                ]);

                return [
                    'acquired' => false,
                    'lock_key' => $globalLockKey,
                    'locks' => [],
                ];
            }
            $locks[] = $globalLock;
        }

        return [
            'acquired' => true,
            'lock_key' => $globalLockKey,
            'locks' => $locks,
        ];
    }

    /**
     * @param array{acquired:bool,lock_key:string,locks:array<int,Lock>} $lockState
     */
    private function releaseRunLocks(array $lockState): void
    {
        foreach (array_reverse($lockState['locks'] ?? []) as $lock) {
            try {
                $lock->release();
            } catch (Throwable) {
                // ignore release errors
            }
        }
    }

    private function stampItemRunContext(BotItem $item, string $runContext): void
    {
        $meta = is_array($item->meta) ? $item->meta : [];
        if ((string) ($meta['run_context'] ?? '') === $runContext) {
            return;
        }

        $meta['run_context'] = $runContext;
        $item->forceFill(['meta' => $meta])->save();
    }

    /**
     * @param array<string,mixed> $stats
     */
    private function recordErrorFingerprint(array &$stats, Throwable $e): void
    {
        $base = sprintf('%s|%s', $e::class, $this->limitText($e->getMessage(), 200));
        $fingerprint = substr(sha1($base), 0, 12);
        $existing = is_array($stats['error_fingerprints'] ?? null) ? $stats['error_fingerprints'] : [];
        $existing[$fingerprint] = (int) ($existing[$fingerprint] ?? 0) + 1;
        $stats['error_fingerprints'] = $existing;
    }

    private function hasAnyTranslatedText(BotItem $item): bool
    {
        return trim((string) $item->title_translated) !== ''
            || trim((string) $item->content_translated) !== '';
    }

    private function nullableString(mixed $value): ?string
    {
        $text = trim((string) $value);
        return $text !== '' ? $text : null;
    }

    private function limitText(string $value, int $maxLength): string
    {
        if ($maxLength <= 0) {
            return '';
        }

        $normalized = preg_replace('/\s+/u', ' ', trim($value)) ?? '';
        if ($normalized === '') {
            return 'n/a';
        }

        if (function_exists('mb_substr')) {
            return mb_substr($normalized, 0, $maxLength);
        }

        return substr($normalized, 0, $maxLength);
    }
}
