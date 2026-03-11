<?php

namespace App\Services\Bots\Concerns;

use App\Enums\BotRunFailureReason;
use App\Enums\BotRunStatus;
use App\Models\BotItem;
use App\Models\BotRun;
use App\Models\BotSource;
use App\Services\Translation\Exceptions\TranslationProviderUnavailableException;
use App\Services\Translation\Exceptions\TranslationTimeoutException;
use Carbon\Carbon;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

trait HandlesBotRunnerInternals
{
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
