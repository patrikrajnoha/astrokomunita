<?php

namespace App\Services\Widgets;

use App\Http\Resources\EventResource;
use App\Models\Event;
use App\Services\Events\PublishedEventQuery;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

class EventWidgetService
{
    private const ECLIPSE_TYPES = ['eclipse', 'eclipse_lunar', 'eclipse_solar'];
    private const METEOR_TYPES = ['meteors', 'meteor_shower'];

    public function __construct(
        private readonly PublishedEventQuery $publishedEventQuery,
    ) {
    }

    /**
     * @return array<string,mixed>
     */
    public function upcoming(): array
    {
        $ttlSeconds = max((int) config('widgets.upcoming_events.cache_ttl_seconds', 120), 1);
        $cacheKey = 'widget:upcoming-events:v1';

        return Cache::remember($cacheKey, now()->addSeconds($ttlSeconds), function (): array {
            $items = $this->basePublishedQuery()
                ->whereNotNull('start_at')
                ->where('start_at', '>=', now())
                ->orderBy('start_at')
                ->orderBy('id')
                ->limit(4)
                ->get(['id', 'title', 'start_at']);

            return [
                'items' => $items->map(static fn (Event $event): array => [
                    'id' => (int) $event->id,
                    'title' => (string) $event->title,
                    'slug' => null,
                    'start_at' => optional($event->start_at)?->toIso8601String(),
                ])->values()->all(),
                'source' => [
                    'provider' => 'astrokomunita_events',
                    'label' => 'Databaza udalosti',
                    'url' => '/events',
                ],
                'generated_at' => now()->toIso8601String(),
            ];
        });
    }

    /**
     * @return array<string,mixed>
     */
    public function nextEclipse(): array
    {
        $ttlSeconds = max((int) config('widgets.next_eclipse.cache_ttl_seconds', 300), 1);
        return $this->spotlightPayload('widget:next-eclipse:v1', $ttlSeconds, self::ECLIPSE_TYPES);
    }

    /**
     * @return array<string,mixed>
     */
    public function nextMeteorShower(): array
    {
        $ttlSeconds = max((int) config('widgets.next_meteor_shower.cache_ttl_seconds', 300), 1);
        return $this->spotlightPayload('widget:next-meteor-shower:v1', $ttlSeconds, self::METEOR_TYPES);
    }

    /**
     * @return array<string,mixed>
     */
    public function nextEvent(): array
    {
        $now = CarbonImmutable::now();
        $base = $this->basePublishedQuery();

        $event = (clone $base)
            ->where(function ($query) use ($now): void {
                $query->where('start_at', '>=', $now)
                    ->orWhere(function ($fallback) use ($now): void {
                        $fallback->whereNull('start_at')
                            ->where('max_at', '>=', $now);
                    });
            })
            ->orderByRaw('COALESCE(start_at, max_at) ASC')
            ->first();

        if (! $event) {
            $event = (clone $base)
                ->orderByRaw('COALESCE(start_at, max_at) DESC')
                ->first();
        }

        if (! $event) {
            return [
                'data' => null,
                'message' => 'Nenasli sa ziadne udalosti.',
            ];
        }

        return [
            'data' => EventResource::make($event)->resolve(),
        ];
    }

    private function basePublishedQuery()
    {
        return $this->publishedEventQuery->base();
    }

    /**
     * @param  list<string>  $types
     * @return array<string,mixed>
     */
    private function spotlightPayload(string $cacheKey, int $ttlSeconds, array $types): array
    {
        return Cache::remember($cacheKey, now()->addSeconds($ttlSeconds), function () use ($types): array {
            $event = $this->resolveUpcomingSpotlightEvent($types);

            return [
                'data' => $event ? $this->mapSpotlightEvent($event) : null,
                'source' => [
                    'provider' => 'astrokomunita_events',
                    'label' => 'Databaza udalosti',
                    'url' => '/events',
                ],
                'generated_at' => now()->toIso8601String(),
            ];
        });
    }

    /**
     * @param  list<string>  $types
     */
    private function resolveUpcomingSpotlightEvent(array $types): ?Event
    {
        return $this->basePublishedQuery()
            ->whereIn('type', $types)
            ->where(function ($query): void {
                $query->where('start_at', '>=', now())
                    ->orWhere(function ($fallback): void {
                        $fallback->whereNull('start_at')
                            ->where('max_at', '>=', now());
                    });
            })
            ->orderByRaw('COALESCE(start_at, max_at) ASC')
            ->orderBy('id')
            ->first([
                'id',
                'title',
                'type',
                'start_at',
                'max_at',
                'end_at',
                'source_name',
                'updated_at',
            ]);
    }

    /**
     * @return array<string,mixed>
     */
    private function mapSpotlightEvent(Event $event): array
    {
        return [
            'id' => (int) $event->id,
            'title' => (string) $event->title,
            'type' => (string) $event->type,
            'start_at' => optional($event->start_at)?->toIso8601String(),
            'max_at' => optional($event->max_at)?->toIso8601String(),
            'end_at' => optional($event->end_at)?->toIso8601String(),
            'updated_at' => optional($event->updated_at)?->toIso8601String(),
            'source' => [
                'name' => (string) $event->source_name,
            ],
        ];
    }
}
