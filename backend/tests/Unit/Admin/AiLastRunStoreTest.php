<?php

namespace Tests\Unit\Admin;

use App\Services\Admin\AiLastRunStore;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AiLastRunStoreTest extends TestCase
{
    public function test_put_stores_only_safe_last_run_fields(): void
    {
        config()->set('events.ai.last_run_cache_ttl_seconds', 604800);
        Cache::flush();

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-03-02 10:00:00', 'UTC'));

        try {
            $store = app(AiLastRunStore::class);

            $payload = $store->put(
                featureName: 'Event Description Generate',
                status: 'success',
                latencyMs: 187,
                entityId: 42,
                retryCount: 2
            );

            $this->assertSame([
                'feature_name',
                'status',
                'latency_ms',
                'retry_count',
                'entity_id',
                'event_id',
                'updated_at',
            ], array_keys($payload));

            $this->assertSame('event_description_generate', $payload['feature_name']);
            $this->assertSame('success', $payload['status']);
            $this->assertSame(187, $payload['latency_ms']);
            $this->assertSame(2, $payload['retry_count']);
            $this->assertSame(42, $payload['entity_id']);
            $this->assertSame(42, $payload['event_id']);
            $this->assertNotEmpty((string) $payload['updated_at']);
            $this->assertArrayNotHasKey('meta', $payload);
            $this->assertArrayNotHasKey('prompt', $payload);
            $this->assertArrayNotHasKey('raw_text', $payload);

            $entityScoped = Cache::get('admin:ai:last_run:event_description_generate:entity:42');
            $featureScoped = Cache::get('admin:ai:last_run:event_description_generate');

            $this->assertSame($payload, $entityScoped);
            $this->assertSame($payload, $featureScoped);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_put_uses_configured_ttl_for_cache_expiration(): void
    {
        config()->set('events.ai.last_run_cache_ttl_seconds', 321);

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-03-02 10:00:00', 'UTC'));

        try {
            $expectedExpiryTimestamp = CarbonImmutable::parse('2026-03-02 10:05:21', 'UTC')->getTimestamp();

            Cache::shouldReceive('put')
                ->once()
                ->withArgs(function (string $key, array $payload, mixed $ttl) use ($expectedExpiryTimestamp): bool {
                    return $key === 'admin:ai:last_run:newsletter_prime_insights'
                        && $payload['status'] === 'success'
                        && $ttl instanceof \DateTimeInterface
                        && $ttl->getTimestamp() === $expectedExpiryTimestamp;
                });

            $store = app(AiLastRunStore::class);
            $store->put(
                featureName: 'newsletter_prime_insights',
                status: 'success',
                latencyMs: 50,
                entityId: null,
                retryCount: 0
            );
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_put_supports_string_entity_id_for_non_event_features(): void
    {
        Cache::flush();

        $store = app(AiLastRunStore::class);
        $payload = $store->put(
            featureName: 'newsletter_copy_draft',
            status: 'fallback',
            latencyMs: 91,
            entityId: 'newsletter',
            retryCount: 1
        );

        $this->assertSame('newsletter', $payload['entity_id']);
        $this->assertNull($payload['event_id']);
        $this->assertSame('fallback', $payload['status']);

        $this->assertSame(
            $payload,
            Cache::get('admin:ai:last_run:newsletter_copy_draft:entity:newsletter')
        );
    }
}
