<?php

namespace App\Http\Controllers\Api\Admin\Concerns;

use App\Enums\BotRunFailureReason;
use App\Enums\BotTranslationStatus;
use App\Models\BotItem;
use App\Models\BotSource;
use App\Services\Translation\Exceptions\TranslationProviderUnavailableException;
use App\Services\Translation\Exceptions\TranslationTimeoutException;
use App\Services\Translation\TranslationOutageSimulationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

trait ManagesBotTranslations
{
    public function translationTest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'text' => 'nullable|string|max:5000',
            'provider' => 'nullable|string|in:auto,libretranslate,ollama',
            'model' => 'nullable|string|max:120',
            'temperature' => 'nullable|numeric|min:0|max:2',
        ]);

        $text = trim((string) ($validated['text'] ?? 'The Solar System contains eight planets orbiting the Sun.'));
        if ($text === '') {
            $text = 'The Solar System contains eight planets orbiting the Sun.';
        }

        $requestedProvider = strtolower(trim((string) ($validated['provider'] ?? 'auto')));
        $requestedModel = trim((string) ($validated['model'] ?? ''));
        $requestedTemperature = array_key_exists('temperature', $validated)
            ? (float) $validated['temperature']
            : null;

        $startedAt = microtime(true);
        $restoreConfig = $this->applyTranslationTestOverrides(
            requestedProvider: $requestedProvider,
            requestedModel: $requestedModel,
            requestedTemperature: $requestedTemperature
        );

        try {
            $result = $this->translationService->translate($text, null, 'sk');
        } catch (Throwable $e) {
            $failureReason = BotRunFailureReason::UNKNOWN->value;
            $statusCode = 422;

            if ($e instanceof TranslationTimeoutException) {
                $failureReason = BotRunFailureReason::TRANSLATION_TIMEOUT->value;
                $statusCode = 504;
            } elseif ($e instanceof TranslationProviderUnavailableException) {
                $failureReason = BotRunFailureReason::PROVIDER_UNAVAILABLE->value;
                $statusCode = 503;
            }

            return response()->json([
                'ok' => false,
                'message' => 'Test prekladu zlyhal.',
                'failure_reason' => $failureReason,
                'error' => $this->truncateErrorText($e->getMessage(), 300),
            ], $statusCode);
        } finally {
            $restoreConfig();
        }

        $meta = is_array($result['meta'] ?? null) ? $result['meta'] : [];
        $translatedText = trim((string) ($result['translated_title'] ?? $result['title_translated'] ?? ''));
        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
        $provider = trim((string) data_get($meta, 'provider', ''));

        return response()->json([
            'ok' => true,
            'provider' => $provider !== '' ? $provider : null,
            'latency_ms' => $durationMs,
            'status' => strtolower(trim((string) ($result['status'] ?? 'done'))),
            'translated_text' => $translatedText !== '' ? $translatedText : null,
            'mode' => $this->nullableString(data_get($meta, 'mode')),
            'quality_flags' => array_values(array_filter(
                array_map('strval', (array) data_get($meta, 'quality_flags', [])),
                static fn (string $flag): bool => trim($flag) !== ''
            )),
            'provider_chain' => array_values(array_filter(
                array_map('strval', (array) data_get($meta, 'provider_chain', [])),
                static fn (string $providerName): bool => trim($providerName) !== ''
            )),
            'meta' => [
                'target_lang' => strtolower(trim((string) data_get($meta, 'target_lang', 'sk'))),
                'model' => $this->nullableString(data_get($meta, 'model')),
                'fallback_used' => (bool) data_get($meta, 'fallback_used', false),
                'quality_retry_count' => (int) data_get($meta, 'quality_retry_count', 0),
                'requested_provider' => $requestedProvider,
                'requested_model' => $this->nullableString($requestedModel),
                'requested_temperature' => $requestedTemperature,
            ],
        ]);
    }

    /**
     * @return \Closure():void
     */
    private function applyTranslationTestOverrides(
        string $requestedProvider,
        string $requestedModel,
        ?float $requestedTemperature
    ): \Closure {
        $keys = [
            'bots.translation.primary',
            'bots.translation.fallback',
            'bots.translation.ollama.model',
            'bots.translation_ollama_model',
            'ai.ollama.model',
            'bots.translation.ollama.temperature',
            'bots.translation_ollama_temperature',
        ];

        $original = [];
        foreach ($keys as $key) {
            $original[$key] = config($key);
        }

        if ($requestedProvider !== '' && $requestedProvider !== 'auto') {
            config()->set('bots.translation.primary', $requestedProvider);
            config()->set('bots.translation.fallback', 'none');
        }

        if ($requestedModel !== '') {
            config()->set('bots.translation.ollama.model', $requestedModel);
            config()->set('bots.translation_ollama_model', $requestedModel);
            config()->set('ai.ollama.model', $requestedModel);
        }

        if ($requestedTemperature !== null) {
            config()->set('bots.translation.ollama.temperature', $requestedTemperature);
            config()->set('bots.translation_ollama_temperature', $requestedTemperature);
        }

        return static function () use ($original): void {
            foreach ($original as $key => $value) {
                config()->set($key, $value);
            }
        };
    }

    public function translationHealth(): JsonResponse
    {
        $provider = strtolower(trim((string) config('bots.translation.primary', 'libretranslate')));
        $fallbackProvider = strtolower(trim((string) config('bots.translation.fallback', 'none')));
        $timeoutSec = max(1, (int) config('bots.translation.timeout_sec', 12));
        $degraded = false;
        $simulateOutageProvider = $this->outageSimulationService->getProvider();

        $baseUrl = match ($provider) {
            'ollama' => trim((string) config('ai.ollama.base_url', config('ai.ollama_base_url', ''))),
            default => trim((string) config('bots.translation.libretranslate.url', '')),
        };

        try {
            $probeResult = $this->runTranslationHealthProbe();
            $result = [
                'ok' => true,
                'error_type' => null,
            ];
            $degraded = (bool) data_get($probeResult, 'meta.fallback_used', false);
        } catch (Throwable $exception) {
            $primaryErrorType = $this->translationHealthErrorType($exception);
            $hasFallback = $fallbackProvider !== '' && $fallbackProvider !== 'none' && $fallbackProvider !== $provider;

            if ($hasFallback) {
                try {
                    $this->runTranslationHealthProbe($fallbackProvider);
                    $degraded = true;
                    $result = [
                        'ok' => true,
                        'error_type' => null,
                        'primary_error_type' => $primaryErrorType,
                    ];
                } catch (Throwable $fallbackException) {
                    $result = [
                        'ok' => false,
                        'error_type' => $this->translationHealthErrorType($fallbackException),
                    ];
                }
            } else {
                $result = [
                    'ok' => false,
                    'error_type' => $primaryErrorType,
                ];
            }
        }

        $translationCounts = BotItem::query()
            ->selectRaw('translation_status, COUNT(*) as total')
            ->groupBy('translation_status')
            ->pluck('total', 'translation_status');
        $doneCount = (int) ($translationCounts[BotTranslationStatus::DONE->value] ?? 0);
        $skippedCount = (int) ($translationCounts[BotTranslationStatus::SKIPPED->value] ?? 0);
        $failedCount = (int) ($translationCounts[BotTranslationStatus::FAILED->value] ?? 0);
        $pendingCount = (int) ($translationCounts[BotTranslationStatus::PENDING->value] ?? 0);
        $processedCount = $doneCount + $skippedCount + $failedCount;
        $totalCount = $processedCount + $pendingCount;
        $progressPercent = $totalCount > 0
            ? (int) round(($processedCount / $totalCount) * 100)
            : 100;

        return response()->json([
            'provider' => $provider,
            'fallback_provider' => $fallbackProvider,
            'base_url' => $baseUrl !== '' ? $baseUrl : null,
            'timeout_sec' => $timeoutSec,
            'simulate_outage_provider' => $simulateOutageProvider,
            'degraded' => $degraded,
            'result' => $result,
            'translation_queue' => [
                'done' => $doneCount,
                'skipped' => $skippedCount,
                'failed' => $failedCount,
                'pending' => $pendingCount,
                'processed' => $processedCount,
                'total' => $totalCount,
                'progress_percent' => $progressPercent,
            ],
        ]);
    }

    public function updateTranslationSimulateOutage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'provider' => 'required|string|in:none,ollama,libretranslate',
        ]);

        $admin = $request->user();
        $changed = $this->outageSimulationService->setProvider((string) $validated['provider']);

        Log::info('Admin updated translation outage simulation provider.', [
            'admin_user_id' => $admin?->id,
            'admin_email' => $admin?->email,
            'setting_key' => TranslationOutageSimulationService::SETTING_KEY,
            'old_value' => $changed['old'],
            'new_value' => $changed['new'],
        ]);

        return response()->json([
            'key' => TranslationOutageSimulationService::SETTING_KEY,
            'old_value' => $changed['old'],
            'new_value' => $changed['new'],
        ]);
    }

    /**
     * @return array<string,mixed>
     */
    private function runTranslationHealthProbe(?string $forceProvider = null): array
    {
        $originalPrimary = (string) config('bots.translation.primary', 'libretranslate');
        $originalFallback = (string) config('bots.translation.fallback', 'none');

        if ($forceProvider !== null) {
            config()->set('bots.translation.primary', strtolower(trim($forceProvider)));
            config()->set('bots.translation.fallback', 'none');
        }

        try {
            return $this->translationService->translate('health check', null, 'sk');
        } finally {
            if ($forceProvider !== null) {
                config()->set('bots.translation.primary', $originalPrimary);
                config()->set('bots.translation.fallback', $originalFallback);
            }
        }
    }

    private function translationHealthErrorType(Throwable $exception): string
    {
        if ($exception instanceof TranslationTimeoutException) {
            return BotRunFailureReason::TRANSLATION_TIMEOUT->value;
        }

        if ($exception instanceof TranslationProviderUnavailableException) {
            return BotRunFailureReason::PROVIDER_UNAVAILABLE->value;
        }

        return BotRunFailureReason::UNKNOWN->value;
    }

    public function retryTranslation(Request $request, string $sourceKey): JsonResponse
    {
        $validated = $request->validate([
            'limit' => 'nullable|integer|min:1|max:100',
            'run_id' => 'nullable|integer|min:1|exists:bot_runs,id',
        ]);

        $normalizedSourceKey = strtolower(trim($sourceKey));
        $source = BotSource::query()->where('key', $normalizedSourceKey)->first();
        if (!$source) {
            return response()->json([
                'message' => sprintf('Bot source "%s" was not found.', $normalizedSourceKey),
            ], 404);
        }

        $limit = (int) ($validated['limit'] ?? (int) $request->query('limit', 10));
        if ($limit <= 0) {
            $limit = 10;
        }
        $runId = isset($validated['run_id']) ? (int) $validated['run_id'] : null;

        $query = BotItem::query()
            ->where('source_id', $source->id)
            ->whereIn('translation_status', [
                BotTranslationStatus::FAILED->value,
                BotTranslationStatus::PENDING->value,
            ])
            ->orderByDesc('fetched_at')
            ->orderByDesc('id');

        if ($runId !== null) {
            $query->where(function (Builder $builder) use ($runId): void {
                $builder
                    ->where('run_id', $runId)
                    ->orWhere('meta->last_seen_run_id', $runId);
            });
        }

        $items = $query->limit($limit)->get();

        $doneCount = 0;
        $skippedCount = 0;
        $failedCount = 0;
        $updatedItemIds = [];

        foreach ($items as $item) {
            $meta = is_array($item->meta) ? $item->meta : [];
            $title = trim((string) $item->title);
            $content = trim((string) ($item->content ?: $item->summary ?: ''));

            if ($title === '' && $content === '') {
                $meta['translation'] = array_replace(
                    is_array($meta['translation'] ?? null) ? $meta['translation'] : [],
                    [
                        'provider' => 'none',
                        'reason' => 'empty_input',
                        'target_lang' => 'sk',
                        'error' => null,
                        'translated_at' => now()->toIso8601String(),
                    ]
                );
                unset($meta['translation_error']);
                $item->forceFill([
                    'translation_status' => BotTranslationStatus::SKIPPED->value,
                    'translation_error' => null,
                    'translation_provider' => 'none',
                    'translated_at' => now(),
                    'meta' => $meta,
                ])->save();

                $skippedCount++;
                $updatedItemIds[] = $item->id;
                continue;
            }

            try {
                $result = $this->translationService->translate($title, $content, 'sk');
                $resultMeta = is_array($result['meta'] ?? null) ? $result['meta'] : [];
                $status = strtolower(trim((string) ($result['status'] ?? BotTranslationStatus::DONE->value)));
                if (!in_array($status, [
                    BotTranslationStatus::DONE->value,
                    BotTranslationStatus::SKIPPED->value,
                    BotTranslationStatus::FAILED->value,
                ], true)) {
                    $status = BotTranslationStatus::DONE->value;
                }

                $meta['translation'] = $resultMeta;
                unset($meta['translation_error']);
                $translationProvider = $this->nullableString(data_get($resultMeta, 'provider'));

                $item->forceFill([
                    'title_translated' => $this->nullableString($result['translated_title'] ?? $result['title_translated'] ?? null),
                    'content_translated' => $this->nullableString($result['translated_content'] ?? $result['content_translated'] ?? null),
                    'translation_status' => $status,
                    'translation_error' => $this->nullableString(data_get($resultMeta, 'error')),
                    'translation_provider' => $translationProvider,
                    'translated_at' => now(),
                    'meta' => $meta,
                ])->save();

                if ($status === BotTranslationStatus::DONE->value) {
                    $doneCount++;
                } elseif ($status === BotTranslationStatus::SKIPPED->value) {
                    $skippedCount++;
                } else {
                    $failedCount++;
                }
                $updatedItemIds[] = $item->id;
            } catch (Throwable $e) {
                $failedCount++;
                $errorMessage = $this->truncateErrorText($e->getMessage(), 300);
                $meta['translation_error'] = $errorMessage;
                Log::warning('Admin bot translation retry failed.', [
                    'source_key' => $normalizedSourceKey,
                    'stable_key' => (string) $item->stable_key,
                    'error' => $errorMessage,
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

        return response()->json([
            'source_key' => $normalizedSourceKey,
            'run_id' => $runId,
            'limit' => $limit,
            'retried_count' => $items->count(),
            'done_count' => $doneCount,
            'skipped_count' => $skippedCount,
            'failed_count' => $failedCount,
            'updated_item_ids' => $updatedItemIds,
        ]);
    }

    public function backfillTranslation(Request $request, string $sourceKey): JsonResponse
    {
        $validated = $request->validate([
            'limit' => 'nullable|integer|min:1|max:100',
            'run_id' => 'nullable|integer|min:1|exists:bot_runs,id',
        ]);

        $normalizedSourceKey = strtolower(trim($sourceKey));
        $source = BotSource::query()->where('key', $normalizedSourceKey)->first();
        if (!$source) {
            return response()->json([
                'message' => sprintf('Bot source "%s" was not found.', $normalizedSourceKey),
            ], 404);
        }

        $limit = (int) ($validated['limit'] ?? (int) $request->query('limit', 10));
        if ($limit <= 0) {
            $limit = 10;
        }
        $runId = isset($validated['run_id']) ? (int) $validated['run_id'] : null;

        try {
            $result = $this->backfillService->backfill($source, $limit, $runId);
        } catch (Throwable $e) {
            Log::warning('Admin bot translation backfill failed.', [
                'source_key' => $normalizedSourceKey,
                'run_id' => $runId,
                'error' => $this->truncateErrorText($e->getMessage(), 240),
            ]);

            return response()->json([
                'source_key' => $normalizedSourceKey,
                'run_id' => $runId,
                'limit' => $limit,
                'scanned' => 0,
                'updated_posts' => 0,
                'skipped' => 0,
                'failed' => 1,
                'failures' => [[
                    'post_id' => null,
                    'reason' => 'backfill_failed',
                ]],
            ], 422);
        }

        return response()->json($result);
    }
}
