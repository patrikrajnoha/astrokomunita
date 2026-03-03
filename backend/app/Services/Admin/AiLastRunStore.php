<?php

namespace App\Services\Admin;

use Illuminate\Support\Facades\Cache;

class AiLastRunStore
{
    private const CACHE_PREFIX = 'admin:ai:last_run:';
    private const DEFAULT_TTL_SECONDS = 2_592_000; // 30 days

    /**
     * @return array<string,mixed>
     */
    public function put(
        string $featureName,
        string $status,
        ?int $latencyMs = null,
        int|string|null $entityId = null,
        ?int $retryCount = null
    ): array {
        $normalizedStatus = $this->normalizeStatus($status);
        $normalizedEntityId = $this->normalizeEntityId($entityId);

        $payload = [
            'feature_name' => $this->normalizeFeatureName($featureName),
            'status' => $normalizedStatus,
            'latency_ms' => $latencyMs !== null ? max(0, (int) $latencyMs) : null,
            'retry_count' => max(0, (int) ($retryCount ?? 0)),
            'entity_id' => $normalizedEntityId,
            'event_id' => is_int($normalizedEntityId) ? $normalizedEntityId : null,
            'updated_at' => now()->toIso8601String(),
        ];

        $ttl = now()->addSeconds($this->ttlSeconds());
        Cache::put($this->key($featureName, $normalizedEntityId), $payload, $ttl);

        if ($normalizedEntityId !== null) {
            Cache::put($this->key($featureName), $payload, $ttl);
        }

        return $payload;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function get(string $featureName, int|string|null $entityId = null): ?array
    {
        $cached = Cache::get($this->key($featureName, $entityId));

        return is_array($cached) ? $cached : null;
    }

    public function ttlSeconds(): int
    {
        $fallback = (int) config('events.ai.insights_cache_ttl_seconds', self::DEFAULT_TTL_SECONDS);
        return max(60, (int) config('events.ai.last_run_cache_ttl_seconds', $fallback));
    }

    private function key(string $featureName, int|string|null $entityId = null): string
    {
        $feature = $this->normalizeFeatureName($featureName);
        $normalizedEntityId = $this->normalizeEntityId($entityId);

        if (is_int($normalizedEntityId)) {
            return sprintf('%s%s:entity:%d', self::CACHE_PREFIX, $feature, $normalizedEntityId);
        }

        if (is_string($normalizedEntityId) && $normalizedEntityId !== '') {
            return sprintf('%s%s:entity:%s', self::CACHE_PREFIX, $feature, $normalizedEntityId);
        }

        return self::CACHE_PREFIX . $feature;
    }

    private function normalizeFeatureName(string $featureName): string
    {
        $value = strtolower(trim($featureName));
        $value = preg_replace('/[^a-z0-9:_-]+/i', '_', $value) ?? $value;
        $value = trim((string) $value, '_');

        return $value !== '' ? $value : 'unknown_feature';
    }

    private function normalizeStatus(string $status): string
    {
        $value = strtolower(trim($status));

        return in_array($value, ['success', 'fallback', 'error', 'idle'], true)
            ? $value
            : 'idle';
    }

    private function normalizeEntityId(int|string|null $entityId): int|string|null
    {
        if (is_int($entityId)) {
            return $entityId > 0 ? $entityId : null;
        }

        $value = trim((string) ($entityId ?? ''));
        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d+$/', $value) === 1) {
            $intValue = (int) $value;
            return $intValue > 0 ? $intValue : null;
        }

        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9:_-]+/i', '_', $value) ?? $value;
        $value = trim((string) $value, '_');

        return $value !== '' ? $value : null;
    }
}
