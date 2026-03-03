<?php

namespace App\Services\Events;

use App\Models\Event;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;

class EventInsightsCacheService
{
    private const CACHE_PREFIX = 'events:description_insights:';
    private const DEFAULT_TTL_SECONDS = 2_592_000; // 30 days

    /**
     * @var array<int,string>
     */
    private const INVALIDATION_COLUMNS = [
        'title',
        'type',
        'start_at',
        'end_at',
        'max_at',
        'visibility',
        'region_scope',
        'source_name',
    ];

    public function key(int $eventId): string
    {
        return self::CACHE_PREFIX . max(0, $eventId);
    }

    public function ttlSeconds(): int
    {
        return max(60, (int) config('events.ai.insights_cache_ttl_seconds', self::DEFAULT_TTL_SECONDS));
    }

    /**
     * @return array<int,string>
     */
    public function invalidationColumns(): array
    {
        return self::INVALIDATION_COLUMNS;
    }

    public function shouldInvalidateForUpdate(Event $event): bool
    {
        return $event->wasChanged($this->invalidationColumns());
    }

    public function invalidate(int|Event $event): void
    {
        $eventId = $event instanceof Event ? (int) $event->id : (int) $event;
        if ($eventId <= 0) {
            return;
        }

        Cache::forget($this->key($eventId));
    }

    public function put(Event $event, string $whyInteresting, string $howToObserve): void
    {
        $eventId = (int) $event->id;
        if ($eventId <= 0) {
            return;
        }

        $payload = [
            'why_interesting' => trim($whyInteresting),
            'how_to_observe' => trim($howToObserve),
            'updated_at' => now()->toIso8601String(),
            'factual_hash' => $this->buildEventHash($event),
        ];

        Cache::put(
            $this->key($eventId),
            $payload,
            now()->addSeconds($this->ttlSeconds())
        );
    }

    /**
     * @return array<string,mixed>|null
     */
    public function get(int $eventId): ?array
    {
        $cached = Cache::get($this->key($eventId));

        return is_array($cached) ? $cached : null;
    }

    /**
     * @param array<string,mixed> $cached
     */
    public function isFreshForEvent(Event $event, array $cached): bool
    {
        $cachedHash = trim((string) ($cached['factual_hash'] ?? ''));
        if ($cachedHash === '') {
            return false;
        }

        return hash_equals($cachedHash, $this->buildEventHash($event));
    }

    public function buildEventHash(Event $event): string
    {
        $payload = $this->buildEventHashInput($event);

        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (! is_string($encoded) || $encoded === '') {
            $encoded = serialize($payload);
        }

        return hash('sha256', $encoded);
    }

    /**
     * Build deterministic facts that are used both for LLM input and hash freshness checks.
     *
     * @return array<string,mixed>
     */
    public function buildFactualPackForHash(Event $event): array
    {
        $timezone = trim((string) config('events.timezone', config('app.timezone', 'Europe/Bratislava')));
        if ($timezone === '') {
            $timezone = 'Europe/Bratislava';
        }

        $pack = [
            'title' => $this->sanitizeFactualString((string) $event->title),
            'type' => $this->sanitizeFactualString((string) $event->type),
            'region_scope' => $this->sanitizeFactualString((string) ($event->region_scope ?? 'global')),
            'source' => $this->sanitizeFactualString((string) ($event->source_name ?? '')),
            'visibility' => $event->visibility !== null ? (int) $event->visibility : null,
            'times' => array_filter([
                'start_at' => $this->buildDateFact($event->start_at, $timezone),
                'max_at' => $this->buildDateFact($event->max_at, $timezone),
                'end_at' => $this->buildDateFact($event->end_at, $timezone),
            ], static fn ($value): bool => is_array($value) && $value !== []),
            'key_values' => $this->extractKeyValues((string) $event->title),
        ];

        $location = $this->sanitizeFactualString((string) ($event->location ?? ''));
        if ($location !== '') {
            $pack['location'] = $location;
        }

        return array_filter($pack, static fn ($value): bool => $value !== null && $value !== '' && $value !== []);
    }

    /**
     * @return array<string,mixed>
     */
    public function buildEventHashInput(Event $event): array
    {
        return $this->sortKeysRecursively($this->buildFactualPackForHash($event));
    }

    /**
     * @return array{iso:string,local:string,timezone:string}|null
     */
    private function buildDateFact(mixed $value, string $timezone): ?array
    {
        $normalized = $this->normalizeDateValue($value);
        if (! $normalized instanceof CarbonInterface) {
            return null;
        }

        $utc = $normalized->clone()->utc();
        $local = $normalized->clone()->setTimezone($timezone);

        return [
            'iso' => $utc->format('Y-m-d\TH:i:sP'),
            'local' => $local->format('d. m. Y H:i'),
            'timezone' => $timezone,
        ];
    }

    private function normalizeDateValue(mixed $value): ?CarbonImmutable
    {
        if ($value instanceof CarbonImmutable) {
            return $value;
        }

        if ($value instanceof CarbonInterface) {
            return CarbonImmutable::instance($value);
        }

        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($trimmed);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<string,int|float>
     */
    private function extractKeyValues(string $title): array
    {
        $values = [];

        if (preg_match('/([0-9][0-9\s.,]{2,})\s*km\b/iu', $title, $match) === 1) {
            $distanceRaw = preg_replace('/\s+/u', '', (string) ($match[1] ?? '')) ?? '';
            $distanceRaw = str_replace(',', '.', $distanceRaw);

            if ($distanceRaw !== '' && is_numeric($distanceRaw)) {
                $values['distance_km'] = (int) round((float) $distanceRaw);
            }
        }

        if (preg_match('/([0-9]+(?:[.,][0-9]+)?)\s*\x{00B0}/u', $title, $match) === 1) {
            $separationRaw = str_replace(',', '.', (string) ($match[1] ?? ''));

            if ($separationRaw !== '' && is_numeric($separationRaw)) {
                $values['separation_deg'] = (float) $separationRaw;
            }
        }

        return $values;
    }

    private function sanitizeFactualString(string $value): string
    {
        $plain = trim(strip_tags($value));
        if ($plain === '') {
            return '';
        }

        $plain = preg_replace('/\s+/u', ' ', $plain) ?? $plain;
        return trim($plain);
    }

    /**
     * @param array<string,mixed> $value
     * @return array<string,mixed>
     */
    private function sortKeysRecursively(array $value): array
    {
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->sortKeysRecursively($item);
            }
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }
}
