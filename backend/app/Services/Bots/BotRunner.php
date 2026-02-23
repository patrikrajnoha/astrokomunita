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

    public function runSource(string $sourceKey, string $runContext = 'manual', bool $forceManualOverride = false): BotRun
    {
        $source = BotSource::query()->where('key', $sourceKey)->firstOrFail();

        return $this->run($source, $runContext, $forceManualOverride);
    }

    public function run(BotSource $source, string $runContext = 'manual', bool $forceManualOverride = false): BotRun
    {
        $context = $this->normalizeRunContext($runContext);
        $lockState = $this->acquireRunLocks($source, $context, $forceManualOverride);

        if (!$lockState['acquired']) {
            $run = $this->runService->startRun($source);
            $stats = $this->createInitialStats();
            $stats['run_locked'] = 1;
            $stats['lock_key'] = $lockState['lock_key'];
            $stats['lock_context'] = $context;
            $stats['manual_override'] = $forceManualOverride ? 1 : 0;

            return $this->runService->finishRun($run, BotRunStatus::SKIPPED, $stats, 'Run skipped because lock is already held.');
        }

        $run = $this->runService->startRun($source);
        $stats = $this->createInitialStats();
        $stats['lock_key'] = $lockState['lock_key'];
        $stats['lock_context'] = $context;
        $stats['manual_override'] = $forceManualOverride ? 1 : 0;

        $status = BotRunStatus::SUCCESS;
        $errorText = null;

        try {
            $rows = $this->fetchRowsForSource($source);
            $stats['fetched_count'] = count($rows);
            $items = $this->dedupeRows($source, $rows, $stats);
            $this->applyTranslationStep($source, $stats);
            $this->publishRows($items, $stats);

            if ($stats['failed_count'] > 0) {
                $status = BotRunStatus::PARTIAL;
            }
        } catch (Throwable $e) {
            $status = BotRunStatus::FAILED;
            $errorText = $e->getMessage();
            $stats['failed_count']++;
        } finally {
            $this->releaseRunLocks($lockState);
        }

        return $this->runService->finishRun($run, $status, $stats, $errorText);
    }

    /**
     * @return array{fetched_count:int,new_count:int,dupes_count:int,published_count:int,skipped_count:int,failed_count:int}
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
     * @param array<int, array{stable_key:string,payload:array<string,mixed>}> $rows
     * @param array<string,int> $stats
     * @return Collection<int, BotItem>
     */
    private function dedupeRows(BotSource $source, array $rows, array &$stats): Collection
    {
        $items = collect();

        foreach ($rows as $row) {
            $item = null;
            try {
                $item = $this->dedupeService->upsertByStableKey($source, $row['stable_key'], $row['payload']);
                if ($item->wasRecentlyCreated) {
                    $stats['new_count']++;
                } else {
                    $stats['dupes_count']++;
                }

                $items->push($item);
            } catch (Throwable $e) {
                $stats['failed_count']++;

                if ($item instanceof BotItem) {
                    $meta = is_array($item->meta) ? $item->meta : [];
                    $meta['last_error'] = $this->limitText($e->getMessage(), 300);

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
     * @param array<string,int> $stats
     */
    private function publishRows(Collection $items, array &$stats): void
    {
        foreach ($items as $item) {
            try {
                $item->refresh();
                $publishResult = $this->publisherService->publishItemToAstroFeed($item);

                if ($publishResult->isPublished()) {
                    $stats['published_count']++;
                } elseif ($publishResult->isSkipped()) {
                    $stats['skipped_count']++;
                }
            } catch (Throwable $e) {
                $stats['failed_count']++;
                $meta = is_array($item->meta) ? $item->meta : [];
                $meta['last_error'] = $this->limitText($e->getMessage(), 300);

                $item->forceFill([
                    'publish_status' => BotPublishStatus::FAILED->value,
                    'meta' => $meta,
                ])->save();
            }
        }
    }

    /**
     * @param array<string,int> $stats
     */
    private function applyTranslationStep(BotSource $source, array &$stats): void
    {
        $items = BotItem::query()
            ->where('source_id', $source->id)
            ->whereNull('post_id')
            ->where('publish_status', BotPublishStatus::PENDING->value)
            ->get();

        foreach ($items as $item) {
            $currentStatus = $item->translation_status?->value ?? (string) $item->translation_status;
            if (!in_array($currentStatus, ['', BotTranslationStatus::PENDING->value, null], true)) {
                continue;
            }

            $langOriginal = strtolower(trim((string) $item->lang_original));
            if ($langOriginal === 'sk' || str_starts_with($langOriginal, 'sk-')) {
                $item->forceFill([
                    'translation_status' => BotTranslationStatus::SKIPPED->value,
                ])->save();

                continue;
            }

            $title = trim((string) $item->title);
            $content = trim((string) ($item->content ?: $item->summary ?: ''));

            if ($title === '' && $content === '') {
                $item->forceFill([
                    'translation_status' => BotTranslationStatus::SKIPPED->value,
                ])->save();

                continue;
            }

            $meta = is_array($item->meta) ? $item->meta : [];
            $cacheKey = sha1('sk|' . $title . '|' . $content);
            $existingCacheKey = trim((string) ($meta['translation_cache_key'] ?? ''));

            if ($existingCacheKey !== '' && hash_equals($existingCacheKey, $cacheKey)) {
                if ($this->hasAnyTranslatedText($item)) {
                    $item->forceFill([
                        'translation_status' => BotTranslationStatus::DONE->value,
                    ])->save();
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

                $item->forceFill([
                    'title_translated' => $this->nullableString($result['translated_title'] ?? $result['title_translated'] ?? null),
                    'content_translated' => $this->nullableString($result['translated_content'] ?? $result['content_translated'] ?? null),
                    'translation_status' => BotTranslationStatus::DONE->value,
                    'meta' => $meta,
                ])->save();
            } catch (Throwable $e) {
                $stats['failed_count']++;
                $meta['translation_error'] = $this->limitText($e->getMessage(), 300);

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

        if (in_array($normalized, ['manual', 'scheduled', 'cli'], true)) {
            return $normalized;
        }

        return 'manual';
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
        if (!($forceManualOverride && $runContext === 'manual')) {
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
