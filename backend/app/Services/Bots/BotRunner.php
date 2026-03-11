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
use App\Services\Bots\Concerns\HandlesBotRunnerInternals;
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
    use HandlesBotRunnerInternals;

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

}
