<?php

namespace App\Services\Bots;

use App\Enums\BotSourceType;
use App\Enums\BotPublishStatus;
use App\Enums\BotRunFailureReason;
use App\Enums\BotRunStatus;
use App\Enums\BotTranslationStatus;
use App\Models\BotItem;
use App\Models\BotRun;
use App\Models\BotSource;
use App\Services\Bots\Contracts\BotTranslationServiceInterface;
use App\Services\Bots\Exceptions\BotSourceRunException;
use App\Services\Translation\Exceptions\TranslationProviderUnavailableException;
use App\Services\Translation\Exceptions\TranslationTimeoutException;
use Carbon\Carbon;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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
        private readonly BotActivityLogService $activityLogService,
        private readonly BotSourceHealthPolicy $sourceHealthPolicy,
        private readonly BotSourceHealthService $sourceHealthService,
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
        $runStartedAt = microtime(true);
        $context = $this->normalizeRunContext($runContext);
        $runMode = $this->normalizeRunMode($mode);
        $normalizedPublishLimit = $this->normalizePublishLimit($publishLimit);
        $runMeta = [
            'run_context' => $context,
            'mode' => $runMode,
            'publish_limit' => $normalizedPublishLimit,
            'runner_host' => gethostname() ?: php_uname('n'),
            'runner_pid' => getmypid() ?: null,
        ];

        $lockState = $this->acquireRunLocks($source, $context, $forceManualOverride);

        if (!$lockState['acquired']) {
            $runMeta['failure_reason'] = BotRunFailureReason::LOCK_CONFLICT->value;
            $run = $this->runService->startRun($source, $runMeta);
            $stats = $this->createInitialStats();
            $stats['run_locked'] = 1;
            $stats['lock_key'] = $lockState['lock_key'];
            $stats['lock_context'] = $context;
            $stats['run_context'] = $context;
            $stats['manual_override'] = $forceManualOverride ? 1 : 0;
            $stats['mode'] = $runMode;
            $stats['publish_limit'] = $normalizedPublishLimit;

            $this->activityLogService->record(
                action: 'run',
                outcome: 'skipped',
                source: $source,
                run: $run,
                reason: BotRunFailureReason::LOCK_CONFLICT->value,
                runContext: $context,
                message: 'Run skipped because lock is already held.',
                meta: [
                    'mode' => $runMode,
                    'publish_limit' => $normalizedPublishLimit,
                ]
            );

            $runMeta = array_replace(
                $runMeta,
                $this->sourceHealthService->recordRunOutcome(
                    $source,
                    BotRunStatus::SKIPPED,
                    $runMeta,
                    'Run skipped because lock is already held.',
                    $this->elapsedMilliseconds($runStartedAt)
                )
            );

            $finalizedRun = $this->finalizeRunSafely(
                $run,
                BotRunStatus::SKIPPED,
                $stats,
                'Run skipped because lock is already held.',
                $runMeta
            );

            return $finalizedRun;
        }

        $run = $this->runService->startRun($source, $runMeta);
        $stats = $this->createInitialStats();
        $stats['lock_key'] = $lockState['lock_key'];
        $stats['lock_context'] = $context;
        $stats['manual_override'] = $forceManualOverride ? 1 : 0;
        $stats['run_context'] = $context;
        $stats['mode'] = $runMode;
        $stats['publish_limit'] = $normalizedPublishLimit;
        $stats['stale_recovered_count'] = 0;

        $this->recoverStaleRunsIfNeeded($source, $run, $stats, $runMeta);

        $inCooldown = $this->isSourceInCooldown($source);
        $cooldownBypassed = $inCooldown && $this->shouldBypassCooldown($context, $forceManualOverride);

        if ($inCooldown && !$cooldownBypassed) {
            $status = BotRunStatus::SKIPPED;
            $stats['skipped_count']++;
            $runMeta = array_replace($runMeta, $this->buildCooldownSkipMeta($source));
            $errorText = (string) ($runMeta['ui_message'] ?? 'Source is temporarily in cooldown due to repeated failures.');

            $this->activityLogService->record(
                action: 'run',
                outcome: 'skipped',
                source: $source,
                run: $run,
                reason: BotRunFailureReason::COOLDOWN_RATE_LIMITED->value,
                runContext: $context,
                message: $errorText,
                meta: [
                    'mode' => $runMode,
                    'publish_limit' => $normalizedPublishLimit,
                    'cooldown_until' => data_get($runMeta, 'cooldown_until'),
                ]
            );
            $this->activityLogService->record(
                action: 'skipped_cooldown',
                outcome: 'skipped',
                source: $source,
                run: $run,
                reason: 'source_cooldown_active',
                runContext: $context,
                message: $errorText,
                meta: [
                    'mode' => $runMode,
                    'publish_limit' => $normalizedPublishLimit,
                    'cooldown_until' => data_get($runMeta, 'cooldown_until'),
                    'retry_after_sec' => data_get($runMeta, 'retry_after_sec'),
                ]
            );

            $this->releaseRunLocks($lockState);

            $runMeta = array_replace(
                $runMeta,
                $this->sourceHealthService->recordRunOutcome(
                    $source,
                    $status,
                    $runMeta,
                    $errorText,
                    $this->elapsedMilliseconds($runStartedAt)
                )
            );

            $finalizedRun = $this->finalizeRunSafely($run, $status, $stats, $errorText, $runMeta);

            return $finalizedRun;
        }

        if ($cooldownBypassed) {
            $runMeta['cooldown_bypassed'] = true;
            if ($source->cooldown_until instanceof Carbon) {
                $runMeta['cooldown_until'] = $source->cooldown_until->copy()->toIso8601String();
            }
        }

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
        } catch (BotSourceRunException $e) {
            $failureReason = strtolower(trim($e->failureReason()));
            $exceptionMeta = $e->contextMeta();
            $runMeta['failure_reason'] = BotRunFailureReason::fromNullable($failureReason)->value;
            $runMeta['provider'] = $this->nullableString((string) ($exceptionMeta['provider'] ?? ''));
            $runMeta['http_status'] = $this->nullableInt($exceptionMeta['http_status'] ?? null);
            $runMeta['message'] = $this->nullableString((string) ($exceptionMeta['message'] ?? $e->getMessage()));
            $runMeta['ui_message'] = $this->nullableString((string) ($exceptionMeta['message'] ?? $e->getMessage()));

            $retryAfter = $this->resolveRetryAfterSeconds($source, $exceptionMeta['retry_after_sec'] ?? null);
            if ($retryAfter !== null) {
                $runMeta['retry_after_sec'] = $retryAfter;
            }

            if ($e->shouldMarkAsSkipped()) {
                $status = BotRunStatus::SKIPPED;
                $stats['skipped_count']++;
            } else {
                $status = BotRunStatus::FAILED;
                $stats['failed_count']++;
            }

            $errorText = $runMeta['ui_message'] ?? $e->getMessage();

            $this->activityLogService->record(
                action: 'run',
                outcome: $status === BotRunStatus::SKIPPED ? 'skipped' : 'failed',
                source: $source,
                run: $run,
                reason: $runMeta['failure_reason'] ?? BotRunFailureReason::UNKNOWN->value,
                runContext: $context,
                message: $this->limitText((string) $errorText, 300),
                meta: [
                    'mode' => $runMode,
                    'publish_limit' => $normalizedPublishLimit,
                    'retry_after_sec' => data_get($runMeta, 'retry_after_sec'),
                    'http_status' => data_get($runMeta, 'http_status'),
                    'provider' => data_get($runMeta, 'provider'),
                ]
            );
        } catch (Throwable $e) {
            $status = BotRunStatus::FAILED;
            $errorText = $e->getMessage();
            $stats['failed_count']++;
            $this->recordErrorFingerprint($stats, $e);
            $runMeta['failure_reason'] = BotRunFailureReason::UNHANDLED_EXCEPTION->value;
            $runMeta['exception_class'] = $e::class;

            $this->activityLogService->record(
                action: 'run',
                outcome: 'failed',
                source: $source,
                run: $run,
                reason: BotRunFailureReason::UNHANDLED_EXCEPTION->value,
                runContext: $context,
                message: $this->limitText($e->getMessage(), 300),
                meta: [
                    'mode' => $runMode,
                    'publish_limit' => $normalizedPublishLimit,
                    'exception_class' => $e::class,
                ]
            );
        } finally {
            $this->releaseRunLocks($lockState);
        }

        if ($status === BotRunStatus::SUCCESS || $status === BotRunStatus::PARTIAL) {
            $this->activityLogService->record(
                action: 'run',
                outcome: $status === BotRunStatus::PARTIAL ? 'partial' : 'success',
                source: $source,
                run: $run,
                reason: null,
                runContext: $context,
                message: null,
                meta: [
                    'mode' => $runMode,
                    'publish_limit' => $normalizedPublishLimit,
                    'stats' => [
                        'fetched_count' => (int) ($stats['fetched_count'] ?? 0),
                        'published_count' => (int) ($stats['published_count'] ?? 0),
                        'skipped_count' => (int) ($stats['skipped_count'] ?? 0),
                        'failed_count' => (int) ($stats['failed_count'] ?? 0),
                    ],
                ]
            );
        }

        $runMeta = array_replace(
            $runMeta,
            $this->sourceHealthService->recordRunOutcome(
                $source,
                $status,
                $runMeta,
                $errorText,
                $this->elapsedMilliseconds($runStartedAt)
            )
        );

        $finalizedRun = $this->finalizeRunSafely($run, $status, $stats, $errorText, $runMeta);

        return $finalizedRun;
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
                $ingestOutcome = 'created';
                $ingestReason = null;

                if ($item->wasRecentlyCreated) {
                    $stats['new_count']++;
                } else {
                    $stats['dupes_count']++;
                    $itemPayloadChanged = $item->wasChanged([
                        'title',
                        'summary',
                        'content',
                        'url',
                        'published_at',
                        'lang_original',
                        'lang_detected',
                    ]);
                    if ($itemPayloadChanged) {
                        $ingestOutcome = 'updated';
                    } else {
                        $ingestOutcome = 'skipped_duplicate';
                        $ingestReason = 'stable_key_exists';
                    }
                }

                $this->activityLogService->record(
                    action: 'ingest',
                    outcome: $ingestOutcome,
                    item: $item,
                    source: $source,
                    run: null,
                    postId: null,
                    reason: $ingestReason,
                    runContext: $runContext,
                    message: null,
                    meta: [
                        'stable_key' => (string) ($row['stable_key'] ?? ''),
                    ]
                );

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

                $this->activityLogService->record(
                    action: 'ingest',
                    outcome: 'failed',
                    item: $item,
                    source: $source,
                    run: null,
                    postId: null,
                    reason: 'exception',
                    runContext: $runContext,
                    message: $this->limitText($e->getMessage(), 300),
                    meta: [
                        'stable_key' => (string) ($row['stable_key'] ?? ''),
                        'exception_class' => $e::class,
                    ]
                );
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
                    if ((string) $publishResult->reason === 'publish_rate_limited') {
                        break;
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

                $this->activityLogService->record(
                    action: 'publish',
                    outcome: 'failed',
                    item: $item,
                    source: null,
                    run: null,
                    postId: null,
                    reason: 'exception',
                    runContext: $runContext,
                    message: $this->limitText($e->getMessage(), 300),
                    meta: [
                        'exception_class' => $e::class,
                    ]
                );
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
            ->whereIn('publish_status', [
                BotPublishStatus::PENDING->value,
                BotPublishStatus::PUBLISHED->value,
            ])
            ->get();

        $sourceKey = strtolower(trim((string) $source->key));

        foreach ($items as $item) {
            $this->stampItemRunContext($item, $runContext);
            $currentStatus = $item->translation_status?->value ?? (string) $item->translation_status;
            $meta = is_array($item->meta) ? $item->meta : [];
            $legacyPlaceholder = $currentStatus === BotTranslationStatus::DONE->value
                && !$this->hasAnyTranslatedText($item)
                && $this->isLegacyTranslationPlaceholder($meta);
            $legacySkippedPlaceholder = $currentStatus === BotTranslationStatus::SKIPPED->value
                && !$this->hasAnyTranslatedText($item)
                && $this->isLegacyTranslationPlaceholder($meta);
            $heuristicSkippedRetry = $currentStatus === BotTranslationStatus::SKIPPED->value
                && !$this->hasAnyTranslatedText($item)
                && $this->isHeuristicSkippedEnglishSource($item, $meta);

            if (!in_array($currentStatus, ['', BotTranslationStatus::PENDING->value, BotTranslationStatus::FAILED->value, null], true)
                && !$legacyPlaceholder
                && !$legacySkippedPlaceholder
                && !$heuristicSkippedRetry) {
                continue;
            }

            $langOriginal = strtolower(trim((string) $item->lang_original));
            if ($langOriginal === 'sk' || str_starts_with($langOriginal, 'sk-')) {
                $meta = is_array($item->meta) ? $item->meta : [];
                $this->markTranslationSkipped($item, $meta, $runContext, 'source_lang_sk', 'source_lang');
                if (config('app.debug')) {
                    Log::debug('Bot translation skipped in runner.', [
                        'source_key' => $sourceKey,
                        'stable_key' => (string) $item->stable_key,
                        'bot_run_id' => $runId,
                        'translation_status' => BotTranslationStatus::SKIPPED->value,
                        'provider' => 'source_lang',
                        'reason' => 'source_lang_sk',
                        'origin_title_hash' => $this->shortHash((string) $item->title),
                        'origin_body_hash' => $this->shortHash((string) ($item->content ?: $item->summary ?: '')),
                        'translated_title_hash' => null,
                        'translated_body_hash' => null,
                    ]);
                }
                continue;
            }

            $title = trim((string) $item->title);
            $content = trim((string) ($item->content ?: $item->summary ?: ''));

            if ($title === '' && $content === '') {
                $meta = is_array($item->meta) ? $item->meta : [];
                $this->markTranslationSkipped($item, $meta, $runContext, 'empty_input', 'none');
                if (config('app.debug')) {
                    Log::debug('Bot translation skipped in runner.', [
                        'source_key' => $sourceKey,
                        'stable_key' => (string) $item->stable_key,
                        'bot_run_id' => $runId,
                        'translation_status' => BotTranslationStatus::SKIPPED->value,
                        'provider' => 'none',
                        'reason' => 'empty_input',
                        'origin_title_hash' => $this->shortHash($title),
                        'origin_body_hash' => $this->shortHash($content),
                        'translated_title_hash' => null,
                        'translated_body_hash' => null,
                    ]);
                }
                continue;
            }

            $isEnglishSource = $langOriginal === 'en' || str_starts_with($langOriginal, 'en-');
            if (!$isEnglishSource && $this->isLikelySlovakText($title, $content)) {
                $meta = is_array($item->meta) ? $item->meta : [];
                $this->markTranslationSkipped($item, $meta, $runContext, 'already_slovak_heuristic', 'heuristic');
                if (config('app.debug')) {
                    Log::debug('Bot translation skipped in runner.', [
                        'source_key' => $sourceKey,
                        'stable_key' => (string) $item->stable_key,
                        'bot_run_id' => $runId,
                        'translation_status' => BotTranslationStatus::SKIPPED->value,
                        'provider' => 'heuristic',
                        'reason' => 'already_slovak_heuristic',
                        'origin_title_hash' => $this->shortHash($title),
                        'origin_body_hash' => $this->shortHash($content),
                        'translated_title_hash' => $this->shortHash($title),
                        'translated_body_hash' => $this->shortHash($content),
                    ]);
                }
                continue;
            }

            $cacheKey = sha1('sk|' . $title . '|' . $content);
            $existingCacheKey = trim((string) ($meta['translation_cache_key'] ?? ''));

            if ($existingCacheKey !== '' && hash_equals($existingCacheKey, $cacheKey)) {
                if ($this->hasAnyTranslatedText($item)) {
                    $meta['run_context'] = $runContext;
                    $item->forceFill([
                        'translation_status' => BotTranslationStatus::DONE->value,
                        'translation_error' => null,
                        'translation_provider' => $this->nullableString((string) data_get($meta, 'translation.provider')),
                        'translated_at' => $item->translated_at ?? now(),
                        'meta' => $meta,
                    ])->save();
                    $stats['translation_done_count'] = (int) ($stats['translation_done_count'] ?? 0) + 1;
                }
                if (config('app.debug')) {
                    Log::debug('Bot translation cache hit in runner.', [
                        'source_key' => $sourceKey,
                        'stable_key' => (string) $item->stable_key,
                        'bot_run_id' => $runId,
                        'translation_status' => $item->translation_status?->value ?? (string) $item->translation_status,
                        'provider' => $item->translation_provider,
                        'reason' => 'cache_hit',
                        'origin_title_hash' => $this->shortHash($title),
                        'origin_body_hash' => $this->shortHash($content),
                        'translated_title_hash' => $this->shortHash((string) $item->title_translated),
                        'translated_body_hash' => $this->shortHash((string) $item->content_translated),
                    ]);
                }

                if ($this->hasAnyTranslatedText($item)) {
                    continue;
                }

                // Legacy rows may carry cache keys from dummy/no-op translation without any translated payload.
                unset($meta['translation_cache_key']);
            }

            try {
                if (config('app.debug')) {
                    Log::debug('Bot translation start in runner.', [
                        'source_key' => $sourceKey,
                        'stable_key' => (string) $item->stable_key,
                        'bot_run_id' => $runId,
                        'translation_status' => BotTranslationStatus::PENDING->value,
                        'provider' => $this->resolveConfiguredTranslationProvider(),
                        'origin_title_hash' => $this->shortHash($title),
                        'origin_body_hash' => $this->shortHash($content),
                    ]);
                }

                $result = $this->translationService->translate($title, $content, 'sk');
                $meta['translation_cache_key'] = $cacheKey;
                unset($meta['translation_error']);

                $translationMeta = is_array($result['meta'] ?? null) ? $result['meta'] : null;
                if ($translationMeta !== null) {
                    $meta['translation'] = $translationMeta;
                }
                $meta['run_context'] = $runContext;
                $translatedTitle = $this->nullableString($result['translated_title'] ?? $result['title_translated'] ?? null);
                $translatedContent = $this->nullableString($result['translated_content'] ?? $result['content_translated'] ?? null);
                $status = strtolower(trim((string) ($result['status'] ?? BotTranslationStatus::DONE->value)));
                if (!in_array($status, [
                    BotTranslationStatus::DONE->value,
                    BotTranslationStatus::SKIPPED->value,
                    BotTranslationStatus::FAILED->value,
                ], true)) {
                    $status = BotTranslationStatus::DONE->value;
                }

                $translationProvider = $this->resolveTranslationProvider($translationMeta, $item->translation_provider);
                $translationError = $this->nullableString(data_get($translationMeta, 'error'));
                $translatedAt = $this->resolveTranslatedAt(data_get($translationMeta, 'translated_at')) ?? now();

                $item->forceFill([
                    'title_translated' => $translatedTitle,
                    'content_translated' => $translatedContent,
                    'translation_status' => $status,
                    'translation_error' => $translationError,
                    'translation_provider' => $translationProvider,
                    'translated_at' => $translatedAt,
                    'meta' => $meta,
                ])->save();

                if ($item->post_id) {
                    try {
                        $this->publisherService->syncLinkedPostFromItem($item, $runContext);
                    } catch (Throwable $syncError) {
                        Log::warning('Bot translation sync to linked post failed.', [
                            'source_key' => $sourceKey,
                            'stable_key' => (string) $item->stable_key,
                            'bot_run_id' => $runId,
                            'post_id' => (int) $item->post_id,
                            'error' => $this->limitText($syncError->getMessage(), 220),
                        ]);
                    }
                }

                if (config('app.debug')) {
                    Log::debug('Bot translation completed in runner.', [
                        'source_key' => $sourceKey,
                        'stable_key' => (string) $item->stable_key,
                        'bot_run_id' => $runId,
                        'translation_status' => $status,
                        'provider' => $translationProvider,
                        'origin_title_hash' => $this->shortHash($title),
                        'origin_body_hash' => $this->shortHash($content),
                        'translated_title_hash' => $this->shortHash($translatedTitle ?? ''),
                        'translated_body_hash' => $this->shortHash($translatedContent ?? ''),
                    ]);
                }

                if ($status === BotTranslationStatus::DONE->value) {
                    $stats['translation_done_count'] = (int) ($stats['translation_done_count'] ?? 0) + 1;
                } elseif ($status === BotTranslationStatus::FAILED->value) {
                    $stats['failed_count']++;
                    $stats['translation_failed_count'] = (int) ($stats['translation_failed_count'] ?? 0) + 1;
                }
            } catch (Throwable $e) {
                $stats['failed_count']++;
                $stats['translation_failed_count'] = (int) ($stats['translation_failed_count'] ?? 0) + 1;
                $this->recordErrorFingerprint($stats, $e);
                $errorMessage = $this->limitText($e->getMessage(), 300);
                $errorType = $this->resolveTranslationErrorType($e);
                $meta['translation_error'] = $errorMessage;
                $meta['translation_error_type'] = $errorType;
                $meta['translation'] = array_replace(
                    is_array($meta['translation'] ?? null) ? $meta['translation'] : [],
                    [
                        'provider' => $this->resolveConfiguredTranslationProvider(),
                        'reason' => $errorType,
                        'target_lang' => 'sk',
                        'translated_at' => now()->toIso8601String(),
                        'error' => $errorMessage,
                    ]
                );
                $meta['run_context'] = $runContext;
                Log::warning('Bot translation failed for item.', [
                    'source_key' => $sourceKey,
                    'stable_key' => (string) $item->stable_key,
                    'bot_run_id' => $runId,
                    'provider' => $this->resolveConfiguredTranslationProvider(),
                    'error_type' => $errorType,
                    'timeout_sec' => max(1, (int) config('bots.translation.timeout_sec', 12)),
                    'error' => $errorMessage,
                    'origin_title_hash' => $this->shortHash($title),
                    'origin_body_hash' => $this->shortHash($content),
                ]);

                $item->forceFill([
                    'translation_status' => BotTranslationStatus::FAILED->value,
                    'translation_error' => $errorMessage,
                    'translation_provider' => null,
                    'translated_at' => now(),
                    'meta' => $meta,
                ])->save();
            }
        }
    }

    /**
     * @param array<string,mixed> $meta
     */
    private function markTranslationSkipped(
        BotItem $item,
        array $meta,
        string $runContext,
        string $reason,
        string $provider
    ): void
    {
        $existingTranslationMeta = is_array($meta['translation'] ?? null) ? $meta['translation'] : [];
        $meta['translation'] = array_replace($existingTranslationMeta, [
            'provider' => $provider,
            'reason' => $reason,
            'target_lang' => 'sk',
            'translated_at' => now()->toIso8601String(),
            'error' => null,
        ]);
        unset($meta['translation_error']);
        $meta['run_context'] = $runContext;

        $item->forceFill([
            'translation_status' => BotTranslationStatus::SKIPPED->value,
            'translation_error' => null,
            'translation_provider' => $provider,
            'translated_at' => now(),
            'meta' => $meta,
        ])->save();
    }

    /**
     * @param array<string,mixed>|null $translationMeta
     */
    private function resolveTranslationProvider(?array $translationMeta, ?string $currentProvider): ?string
    {
        $candidate = $this->nullableString(data_get($translationMeta, 'provider'));
        if ($candidate !== null) {
            return strtolower($candidate);
        }

        $candidate = $this->nullableString(data_get($translationMeta, 'provider_content'));
        if ($candidate !== null) {
            return strtolower($candidate);
        }

        $candidate = $this->nullableString(data_get($translationMeta, 'provider_title'));
        if ($candidate !== null) {
            return strtolower($candidate);
        }

        return $this->nullableString($currentProvider);
    }

    private function resolveTranslatedAt(mixed $value): ?Carbon
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value;
        }

        $normalized = trim((string) $value);
        if ($normalized === '') {
            return null;
        }

        try {
            return Carbon::parse($normalized);
        } catch (Throwable) {
            return null;
        }
    }

    private function resolveConfiguredTranslationProvider(): string
    {
        $configuredPrimary = strtolower(trim((string) config('bots.translation.primary', '')));
        if ($configuredPrimary !== '') {
            return $configuredPrimary;
        }

        return strtolower(trim((string) config('bots.translation_provider', 'unknown')));
    }

    private function isLikelySlovakText(string $title, string $content): bool
    {
        $combined = trim($title . ' ' . $content);
        if ($combined === '') {
            return false;
        }

        if ($this->stringLength($combined) < 60) {
            return false;
        }

        preg_match_all('/[^\x00-\x7F]/u', $combined, $matches);
        $diacriticsCount = count($matches[0] ?? []);

        return $diacriticsCount >= 5;
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

    private function shouldBypassCooldown(string $runContext, bool $forceManualOverride): bool
    {
        if (!$forceManualOverride) {
            return false;
        }

        return in_array($runContext, ['manual', 'admin', 'cli'], true);
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
        $ttlSeconds = max(60, (int) config('bots.run_lock_ttl_seconds', 600));
        $locks = [];

        $contextLockKey = $this->buildContextLockKey($runContext, $sourceKey);
        $contextLock = Cache::lock($contextLockKey, $ttlSeconds);
        if (!$contextLock->get()) {
            return [
                'acquired' => false,
                'lock_key' => $contextLockKey,
                'locks' => [],
            ];
        }
        $locks[] = $contextLock;

        $globalLockKey = $this->buildGlobalLockKey($sourceKey);
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

    /**
     * @param array<string,mixed> $meta
     */
    private function isLegacyTranslationPlaceholder(array $meta): bool
    {
        $provider = strtolower(trim((string) data_get($meta, 'translation.provider', '')));
        $reason = strtolower(trim((string) data_get($meta, 'translation.reason', '')));

        return in_array($provider, ['dummy', 'none'], true)
            || $reason === 'translation_not_enabled';
    }

    /**
     * @param array<string,mixed> $meta
     */
    private function isHeuristicSkippedEnglishSource(BotItem $item, array $meta): bool
    {
        $reason = strtolower(trim((string) data_get($meta, 'translation.reason', '')));
        if ($reason !== 'already_slovak_heuristic') {
            return false;
        }

        $langOriginal = strtolower(trim((string) $item->lang_original));
        return $langOriginal === 'en' || str_starts_with($langOriginal, 'en-');
    }

    private function nullableString(mixed $value): ?string
    {
        $text = trim((string) $value);
        return $text !== '' ? $text : null;
    }

    private function shortHash(string $value): ?string
    {
        $normalized = trim($value);
        if ($normalized === '') {
            return null;
        }

        return substr(sha1($normalized), 0, 8);
    }

    private function stringLength(string $value): int
    {
        if (function_exists('mb_strlen')) {
            return mb_strlen($value);
        }

        return strlen($value);
    }

    private function isSourceInCooldown(BotSource $source): bool
    {
        return $this->sourceHealthPolicy->isInCooldown($source);
    }

    /**
     * @return array<string,mixed>
     */
    private function buildCooldownSkipMeta(BotSource $source): array
    {
        $cooldownUntil = $source->cooldown_until instanceof Carbon
            ? $source->cooldown_until->copy()
            : now();
        $retryAfter = max(0, now()->diffInSeconds($cooldownUntil, false));
        $sourceLabel = trim((string) ($source->name ?? '')) !== ''
            ? trim((string) $source->name)
            : (string) $source->key;

        return [
            'failure_reason' => BotRunFailureReason::COOLDOWN_RATE_LIMITED->value,
            'cooldown_until' => $cooldownUntil->toIso8601String(),
            'retry_after_sec' => $retryAfter,
            'message' => sprintf(
                'Source "%s" is in cooldown until %s.',
                $sourceLabel,
                $cooldownUntil->toIso8601String()
            ),
            'ui_message' => sprintf(
                'Source "%s" je v cooldowne do %s.',
                $sourceLabel,
                $cooldownUntil->toIso8601String()
            ),
        ];
    }

    private function resolveRetryAfterSeconds(BotSource $source, mixed $value): ?int
    {
        if (is_numeric($value)) {
            $seconds = (int) $value;
            if ($seconds > 0) {
                return $seconds;
            }
        }

        $nextFailures = max(1, (int) ($source->consecutive_failures ?? 0) + 1);
        $policySeconds = $this->sourceHealthPolicy->cooldownSecondsForFailures($nextFailures);

        return $policySeconds > 0 ? $policySeconds : null;
    }

    private function elapsedMilliseconds(float $startedAt): int
    {
        return max(0, (int) round((microtime(true) - $startedAt) * 1000));
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

    private function nullableInt(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return null;
    }

    private function resolveTranslationErrorType(Throwable $exception): string
    {
        if ($exception instanceof TranslationTimeoutException) {
            return BotRunFailureReason::TRANSLATION_TIMEOUT->value;
        }

        if ($exception instanceof TranslationProviderUnavailableException) {
            return BotRunFailureReason::PROVIDER_UNAVAILABLE->value;
        }

        return BotRunFailureReason::UNKNOWN->value;
    }

    /**
     * @param array<string,mixed> $stats
     * @param array<string,mixed> $meta
     */
    private function finalizeRunSafely(
        BotRun $run,
        BotRunStatus|string $status,
        array $stats,
        ?string $errorText,
        array $meta
    ): BotRun {
        try {
            return $this->runService->finishRun($run, $status, $stats, $errorText, $meta);
        } catch (Throwable $exception) {
            Log::error('Bot run finish failed, applying direct fallback update.', [
                'run_id' => $run->id,
                'status' => $status instanceof BotRunStatus ? $status->value : (string) $status,
                'error' => $this->limitText($exception->getMessage(), 240),
            ]);

            $statusValue = $status instanceof BotRunStatus ? $status->value : strtolower(trim((string) $status));
            $mergedMeta = array_replace(is_array($run->meta) ? $run->meta : [], $meta);

            DB::table('bot_runs')
                ->where('id', $run->id)
                ->update([
                    'finished_at' => now(),
                    'status' => $statusValue,
                    'stats' => json_encode($stats, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'meta' => $mergedMeta !== [] ? json_encode($mergedMeta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                    'error_text' => $errorText,
                    'updated_at' => now(),
                ]);

            return BotRun::query()->find($run->id) ?? $run;
        }
    }

    /**
     * @param array<string,mixed> $stats
     * @param array<string,mixed> $runMeta
     */
    private function recoverStaleRunsIfNeeded(BotSource $source, BotRun $run, array &$stats, array &$runMeta): void
    {
        $staleMinutes = max(1, (int) config('bots.stale_run_recovery_minutes', 5));
        $recoveredCount = $this->runService->recoverStaleRunsForSource($source, $run->id, $staleMinutes);

        if ($recoveredCount <= 0) {
            return;
        }

        $stats['stale_recovered_count'] = $recoveredCount;
        $runMeta['stale_recovered_count'] = $recoveredCount;

        $sourceKey = strtolower(trim((string) $source->key));
        $this->releaseKnownSourceLocks($sourceKey);

        Log::warning('Recovered stale bot runs before executing a new run.', [
            'source_key' => $sourceKey,
            'current_run_id' => $run->id,
            'recovered_count' => $recoveredCount,
            'stale_minutes' => $staleMinutes,
        ]);
    }

    private function releaseKnownSourceLocks(string $sourceKey): void
    {
        if ($sourceKey === '') {
            return;
        }

        $lockKeys = [
            $this->buildGlobalLockKey($sourceKey),
            $this->buildContextLockKey('manual', $sourceKey),
            $this->buildContextLockKey('admin', $sourceKey),
            $this->buildContextLockKey('scheduled', $sourceKey),
            $this->buildContextLockKey('cli', $sourceKey),
        ];

        foreach ($lockKeys as $lockKey) {
            try {
                Cache::lock($lockKey)->forceRelease();
            } catch (Throwable) {
                // ignore lock cleanup errors
            }
        }
    }

    private function buildContextLockKey(string $runContext, string $sourceKey): string
    {
        return sprintf('bots:run:%s:%s', strtolower(trim($runContext)), strtolower(trim($sourceKey)));
    }

    private function buildGlobalLockKey(string $sourceKey): string
    {
        return sprintf('bots:run:%s', strtolower(trim($sourceKey)));
    }
}
