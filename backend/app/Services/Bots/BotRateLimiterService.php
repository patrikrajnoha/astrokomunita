<?php

namespace App\Services\Bots;

use Illuminate\Support\Facades\RateLimiter;

class BotRateLimiterService
{
    /**
     * @return array{
     *   identity:string,
     *   key:?string,
     *   enabled:bool,
     *   limited:bool,
     *   window_sec:int,
     *   max_attempts:int,
     *   retry_after_sec:int,
     *   remaining_attempts:int
     * }
     */
    public function resolvePublishState(string $botIdentity): array
    {
        return $this->resolveStateForGroup($botIdentity, 'publish');
    }

    /**
     * @return array{
     *   identity:string,
     *   key:?string,
     *   enabled:bool,
     *   limited:bool,
     *   window_sec:int,
     *   max_attempts:int,
     *   retry_after_sec:int,
     *   remaining_attempts:int
     * }
     */
    public function resolveScheduleState(string $botIdentity): array
    {
        return $this->resolveStateForGroup($botIdentity, 'schedule');
    }

    /**
     * @param array{
     *   key:?string,
     *   limited:bool,
     *   window_sec:int
     * } $state
     */
    public function consume(array $state): void
    {
        if (($state['limited'] ?? false) === true) {
            return;
        }

        $key = trim((string) ($state['key'] ?? ''));
        if ($key === '') {
            return;
        }

        $windowSeconds = max(1, (int) ($state['window_sec'] ?? 0));
        RateLimiter::hit($key, $windowSeconds);
    }

    /**
     * @return array{
     *   identity:string,
     *   key:?string,
     *   enabled:bool,
     *   limited:bool,
     *   window_sec:int,
     *   max_attempts:int,
     *   retry_after_sec:int,
     *   remaining_attempts:int
     * }
     */
    private function resolveStateForGroup(string $botIdentity, string $group): array
    {
        $identity = strtolower(trim($botIdentity));
        if ($identity === '') {
            $identity = 'unknown';
        }

        $enabled = (bool) config(sprintf('bots.%s_rate_limit.enabled', $group), true);
        $windowSeconds = max(1, (int) config(sprintf('bots.%s_rate_limit.window_seconds', $group), 3600));
        $defaultMaxAttempts = max(0, (int) config(sprintf('bots.%s_rate_limit.default_max_attempts', $group), 30));
        $maxAttempts = max(
            0,
            (int) config(sprintf('bots.%s_rate_limit.identities.%s', $group, $identity), $defaultMaxAttempts)
        );

        if (!$enabled || $maxAttempts <= 0) {
            return [
                'identity' => $identity,
                'key' => null,
                'enabled' => $enabled,
                'limited' => false,
                'window_sec' => $windowSeconds,
                'max_attempts' => $maxAttempts,
                'retry_after_sec' => 0,
                'remaining_attempts' => $maxAttempts,
            ];
        }

        $key = sprintf('bots:%s_rate:%s', $group, $identity);
        $limited = RateLimiter::tooManyAttempts($key, $maxAttempts);
        $retryAfter = $limited ? max(1, (int) RateLimiter::availableIn($key)) : 0;
        $remaining = max(0, (int) RateLimiter::remaining($key, $maxAttempts));

        return [
            'identity' => $identity,
            'key' => $key,
            'enabled' => true,
            'limited' => $limited,
            'window_sec' => $windowSeconds,
            'max_attempts' => $maxAttempts,
            'retry_after_sec' => $retryAfter,
            'remaining_attempts' => $remaining,
        ];
    }
}

