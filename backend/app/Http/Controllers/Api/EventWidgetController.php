<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\Events\PublishedEventQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class EventWidgetController extends Controller
{
    private const ECLIPSE_TYPES = ['eclipse', 'eclipse_lunar', 'eclipse_solar'];
    private const METEOR_TYPES = ['meteors', 'meteor_shower'];

    public function __construct(
        private readonly PublishedEventQuery $publishedEventQuery,
    ) {
    }

    public function upcoming(): JsonResponse
    {
        $ttlSeconds = max((int) config('widgets.upcoming_events.cache_ttl_seconds', 120), 1);
        $cacheKey = 'widget:upcoming-events:v1';

        $payload = Cache::remember($cacheKey, now()->addSeconds($ttlSeconds), function (): array {
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
                    // Events currently do not have a dedicated slug column.
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

        return response()->json($payload);
    }

    public function nextEclipse(): JsonResponse
    {
        $ttlSeconds = max((int) config('widgets.next_eclipse.cache_ttl_seconds', 300), 1);
        return $this->spotlightResponse('widget:next-eclipse:v1', $ttlSeconds, self::ECLIPSE_TYPES);
    }

    public function nextMeteorShower(): JsonResponse
    {
        $ttlSeconds = max((int) config('widgets.next_meteor_shower.cache_ttl_seconds', 300), 1);
        return $this->spotlightResponse('widget:next-meteor-shower:v1', $ttlSeconds, self::METEOR_TYPES);
    }

    private function basePublishedQuery()
    {
        return $this->publishedEventQuery->base();
    }

    /**
     * @param  list<string>  $types
     */
    private function spotlightResponse(string $cacheKey, int $ttlSeconds, array $types): JsonResponse
    {
        $payload = Cache::remember($cacheKey, now()->addSeconds($ttlSeconds), function () use ($types): array {
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

        return response()->json($payload);
    }

    /**
     * @param  list<string>  $types
     */
    private function resolveUpcomingSpotlightEvent(array $types): ?Event
    {
        return $this->basePublishedQuery()
            ->whereIn('type', $types)
            ->where(function ($query) {
                $query->where('start_at', '>=', now())
                    ->orWhere(function ($fallback) {
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
