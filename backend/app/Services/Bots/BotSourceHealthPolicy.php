<?php

namespace App\Services\Bots;

use App\Models\BotSource;
use Carbon\CarbonInterface;

class BotSourceHealthPolicy
{
    /**
     * @return array{
     *   status:string,
     *   is_dead:bool,
     *   in_cooldown:bool,
     *   cooldown_until:?string,
     *   consecutive_failures:int
     * }
     */
    public function snapshot(BotSource $source, ?CarbonInterface $now = null): array
    {
        $currentNow = $now?->copy() ?? now();
        $failures = max(0, (int) ($source->consecutive_failures ?? 0));
        $isDead = $this->isDead($source, $currentNow, $failures);
        $inCooldown = $this->isInCooldown($source, $currentNow);

        return [
            'status' => $this->statusFor($failures, $isDead),
            'is_dead' => $isDead,
            'in_cooldown' => $inCooldown,
            'cooldown_until' => $source->cooldown_until?->toIso8601String(),
            'consecutive_failures' => $failures,
        ];
    }

    public function isInCooldown(BotSource $source, ?CarbonInterface $now = null): bool
    {
        $currentNow = $now?->copy() ?? now();
        $cooldownUntil = $source->cooldown_until;
        if (!$cooldownUntil instanceof CarbonInterface) {
            return false;
        }

        return $cooldownUntil->gt($currentNow);
    }

    public function cooldownSecondsForFailures(int $consecutiveFailures): int
    {
        $failures = max(0, $consecutiveFailures);
        if ($failures <= 2) {
            return 0;
        }
        if ($failures <= 4) {
            return max(60, (int) config('bots.health.cooldown_short_seconds', 5 * 60));
        }
        if ($failures <= 6) {
            return max(60, (int) config('bots.health.cooldown_medium_seconds', 30 * 60));
        }

        return max(60, (int) config('bots.health.cooldown_long_seconds', 2 * 60 * 60));
    }

    public function isDead(BotSource $source, ?CarbonInterface $now = null, ?int $failureCount = null): bool
    {
        $failures = max(0, $failureCount ?? (int) ($source->consecutive_failures ?? 0));
        if ($failures >= $this->deadFailureThreshold()) {
            return true;
        }

        if ($failures <= 0) {
            return false;
        }

        $currentNow = $now?->copy() ?? now();
        $cutoff = $currentNow->copy()->subDays($this->deadNoSuccessDays());
        $lastSuccessAt = $source->last_success_at;
        if ($lastSuccessAt instanceof CarbonInterface) {
            return $lastSuccessAt->lte($cutoff);
        }

        $lastErrorAt = $source->last_error_at;
        if ($lastErrorAt instanceof CarbonInterface) {
            return $lastErrorAt->lte($cutoff);
        }

        return false;
    }

    public function failThreshold(): int
    {
        return max(1, (int) config('bots.health.fail_threshold', 5));
    }

    public function deadFailureThreshold(): int
    {
        return max(1, (int) config('bots.health.dead_failure_threshold', 20));
    }

    public function deadNoSuccessDays(): int
    {
        return max(1, (int) config('bots.health.dead_no_success_days', 7));
    }

    /**
     * @return array{
     *   status:string,
     *   is_dead:bool,
     *   should_disable:bool,
     *   cooldown_seconds:int,
     *   cooldown_until:?CarbonInterface
     * }
     */
    public function resolveFailureTransition(
        BotSource $source,
        int $nextFailures,
        ?int $retryAfterSeconds = null,
        ?CarbonInterface $now = null
    ): array {
        $currentNow = $now?->copy() ?? now();
        $policyCooldown = $this->cooldownSecondsForFailures($nextFailures);
        $retryAfter = max(0, (int) ($retryAfterSeconds ?? 0));
        $cooldownSeconds = max($policyCooldown, $retryAfter);
        $cooldownUntil = $cooldownSeconds > 0
            ? $currentNow->copy()->addSeconds($cooldownSeconds)
            : null;

        $isDead = $this->isDead($source, $currentNow, $nextFailures);

        return [
            'status' => $this->statusFor($nextFailures, $isDead),
            'is_dead' => $isDead,
            'should_disable' => $isDead,
            'cooldown_seconds' => $cooldownSeconds,
            'cooldown_until' => $cooldownUntil,
        ];
    }

    private function statusFor(int $failures, bool $isDead): string
    {
        if ($isDead) {
            return 'dead';
        }

        if ($failures >= $this->failThreshold()) {
            return 'fail';
        }

        if ($failures > 0) {
            return 'warn';
        }

        return 'ok';
    }
}

