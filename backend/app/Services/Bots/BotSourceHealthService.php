<?php

namespace App\Services\Bots;

use App\Enums\BotRunFailureReason;
use App\Enums\BotRunStatus;
use App\Models\BotSource;
use Illuminate\Support\Facades\Log;
use Throwable;

class BotSourceHealthService
{
    /**
     * @param array<string,mixed> $runMeta
     */
    public function recordRunOutcome(
        BotSource $source,
        BotRunStatus|string $status,
        array $runMeta = [],
        ?string $errorText = null,
        ?int $latencyMs = null
    ): void {
        try {
            $statusValue = strtolower(trim((string) ($status instanceof BotRunStatus ? $status->value : $status)));
            $failureReason = strtolower(trim((string) ($runMeta['failure_reason'] ?? '')));
            $statusCode = $this->nullableInt($runMeta['http_status'] ?? null);
            $latency = max(0, (int) ($latencyMs ?? 0));

            if (in_array($statusValue, [BotRunStatus::SUCCESS->value, BotRunStatus::PARTIAL->value], true)) {
                $this->markSuccess($source, $latency, $statusCode);

                return;
            }

            if ($failureReason === BotRunFailureReason::LOCK_CONFLICT->value) {
                $source->forceFill([
                    'last_run_at' => now(),
                ])->save();

                return;
            }

            $message = $this->firstNonEmpty([
                (string) ($runMeta['ui_message'] ?? ''),
                (string) ($runMeta['message'] ?? ''),
                (string) $errorText,
            ]);
            if ($statusCode === null && in_array($failureReason, [
                BotRunFailureReason::RATE_LIMITED->value,
                BotRunFailureReason::COOLDOWN_RATE_LIMITED->value,
                BotRunFailureReason::NEEDS_API_KEY->value,
            ], true)) {
                $statusCode = 429;
            }

            $this->markFailure($source, $message, $statusCode, $latency);
        } catch (Throwable $exception) {
            Log::warning('Failed to update bot source health.', [
                'source_id' => $source->id,
                'source_key' => (string) $source->key,
                'error' => $this->limitText($exception->getMessage(), 220),
            ]);
        }
    }

    public function markSuccess(BotSource $source, int $latencyMs = 0, ?int $statusCode = null): void
    {
        $source->forceFill([
            'last_run_at' => now(),
            'last_success_at' => now(),
            'consecutive_failures' => 0,
            'last_error_message' => null,
            'last_status_code' => $statusCode ?? 200,
            'avg_latency_ms' => $this->nextLatencyAverage((int) ($source->avg_latency_ms ?? 0), $latencyMs),
        ])->save();
    }

    public function markFailure(BotSource $source, ?string $message, ?int $statusCode = null, int $latencyMs = 0): void
    {
        $currentFailures = max(0, (int) ($source->consecutive_failures ?? 0));

        $source->forceFill([
            'last_run_at' => now(),
            'last_error_at' => now(),
            'last_error_message' => $this->limitText((string) $message, 500),
            'consecutive_failures' => $currentFailures + 1,
            'last_status_code' => $statusCode,
            'avg_latency_ms' => $this->nextLatencyAverage((int) ($source->avg_latency_ms ?? 0), $latencyMs),
        ])->save();
    }

    private function nextLatencyAverage(int $currentAverage, int $latencyMs): ?int
    {
        $current = max(0, $currentAverage);
        $next = max(0, $latencyMs);

        if ($current <= 0 && $next <= 0) {
            return null;
        }
        if ($current <= 0) {
            return $next;
        }
        if ($next <= 0) {
            return $current;
        }

        return (int) round((($current * 4) + $next) / 5);
    }

    /**
     * @param array<int,string> $values
     */
    private function firstNonEmpty(array $values): ?string
    {
        foreach ($values as $value) {
            $normalized = trim($value);
            if ($normalized !== '') {
                return $normalized;
            }
        }

        return null;
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

    private function limitText(string $value, int $maxLength): ?string
    {
        $normalized = trim($value);
        if ($normalized === '' || $maxLength <= 0) {
            return null;
        }

        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($normalized) <= $maxLength) {
                return $normalized;
            }

            return mb_substr($normalized, 0, $maxLength);
        }

        if (strlen($normalized) <= $maxLength) {
            return $normalized;
        }

        return substr($normalized, 0, $maxLength);
    }
}
