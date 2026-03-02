<?php

namespace App\Services;

use App\Models\Event;
use App\Models\MonthlyFeaturedEvent;
use App\Services\Events\PublishedEventQuery;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class FeaturedEventsResolver
{
    public const DEFAULT_FALLBACK_LIMIT = 4;
    public const FALLBACK_CACHE_TTL_SECONDS = 3600;

    public function __construct(
        private readonly EventCalendarLinksService $calendarLinks,
        private readonly PublishedEventQuery $publishedEventQuery,
    ) {
    }

    /**
     * @return array{month_key:string,mode:'admin'|'fallback',fallback_reason:?string,events:array<int,array<string,mixed>>}
     */
    public function resolveForMonth(string $monthKey, int $fallbackLimit = self::DEFAULT_FALLBACK_LIMIT, bool $refreshFallback = false): array
    {
        $adminEvents = $this->adminSelectionForMonth($monthKey);
        if ($adminEvents !== []) {
            return [
                'month_key' => $monthKey,
                'mode' => 'admin',
                'fallback_reason' => null,
                'events' => $adminEvents,
            ];
        }

        $fallbackEvents = $this->fallbackPreviewForMonth($monthKey, $fallbackLimit, $refreshFallback);

        return [
            'month_key' => $monthKey,
            'mode' => 'fallback',
            'fallback_reason' => $fallbackEvents['events'] === [] ? 'no_events_in_month' : 'no_admin_selection',
            'events' => array_map(
                static function (array $event): array {
                    unset($event['fallback_score']);
                    return $event;
                },
                $fallbackEvents['events']
            ),
        ];
    }

    /**
     * @return array{month_key:string,limit:int,events:array<int,array<string,mixed>>}
     */
    public function fallbackPreviewForMonth(string $monthKey, int $limit = self::DEFAULT_FALLBACK_LIMIT, bool $refresh = false): array
    {
        $safeLimit = max(1, min($limit, 10));
        $cacheKey = $this->fallbackCacheKey($monthKey, $safeLimit);

        if ($refresh) {
            Cache::forget($cacheKey);
        }

        /** @var array<int,array<string,mixed>> $events */
        $events = Cache::remember($cacheKey, self::FALLBACK_CACHE_TTL_SECONDS, function () use ($monthKey, $safeLimit): array {
            return $this->buildFallbackEventsForMonth($monthKey, $safeLimit);
        });

        return [
            'month_key' => $monthKey,
            'limit' => $safeLimit,
            'events' => $events,
        ];
    }

    public function forgetFallbackCache(string $monthKey): void
    {
        foreach ([1, 2, 3, 4, 5, 6, 7, 8, 9, 10] as $limit) {
            Cache::forget($this->fallbackCacheKey($monthKey, $limit));
        }
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function adminSelectionForMonth(string $monthKey): array
    {
        return MonthlyFeaturedEvent::query()
            ->with(['event:id,title,start_at,end_at'])
            ->where('is_active', true)
            ->where(function (Builder $query) use ($monthKey): void {
                $query->where('month_key', $monthKey);

                if ($this->shouldIncludeLegacyRows($monthKey)) {
                    $query->orWhereNull('month_key');
                }
            })
            ->orderBy('position')
            ->orderBy('id')
            ->limit(MarkYourCalendarPopupService::MAX_ACTIVE_ITEMS)
            ->get()
            ->map(function (MonthlyFeaturedEvent $item): ?array {
                return $this->mapEvent($item->event);
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Deterministic fallback scoring based on available event fields:
     * - +50 for major event types (eclipse, meteor shower, conjunction, planetary events)
     * - +20 for curated source (manual/curated)
     * - +5 when event has explicit end_at (richer schedule data)
     * - +0..10 soonness score inside month (earlier in month => higher)
     * Tie-break: starts_at ASC, then id ASC.
     *
     * @return array<int,array<string,mixed>>
     */
    private function buildFallbackEventsForMonth(string $monthKey, int $limit): array
    {
        [$monthStartUtc, $monthEndUtc, $monthStartLocal] = $this->monthBoundaries($monthKey);

        $ranked = $this->publishedMonthEventsQuery($monthStartUtc, $monthEndUtc)
            ->get(['id', 'title', 'type', 'source_name', 'start_at', 'end_at', 'max_at'])
            ->map(function (Event $event) use ($monthStartLocal): array {
                $eventMoment = $this->eventMoment($event);
                $score = $this->scoreFallbackEvent($event, $eventMoment, $monthStartLocal);

                return [
                    'event' => $event,
                    'score' => $score,
                    'event_moment' => $eventMoment,
                ];
            })
            ->sort(function (array $left, array $right): int {
                if ($left['score'] !== $right['score']) {
                    return $right['score'] <=> $left['score'];
                }

                $leftMoment = $left['event_moment'];
                $rightMoment = $right['event_moment'];
                if ($leftMoment && $rightMoment) {
                    $momentOrder = $leftMoment->getTimestamp() <=> $rightMoment->getTimestamp();
                    if ($momentOrder !== 0) {
                        return $momentOrder;
                    }
                }

                return (int) $left['event']->id <=> (int) $right['event']->id;
            })
            ->take($limit)
            ->values();

        return $ranked
            ->map(function (array $row): array {
                $mapped = $this->mapEvent($row['event']) ?? [];
                $mapped['fallback_score'] = (int) $row['score'];

                return $mapped;
            })
            ->filter(static fn (array $row): bool => isset($row['id']))
            ->values()
            ->all();
    }

    private function scoreFallbackEvent(Event $event, ?CarbonImmutable $eventMoment, CarbonImmutable $monthStartLocal): int
    {
        $score = 0;

        if (in_array((string) $event->type, [
            'eclipse',
            'eclipse_lunar',
            'eclipse_solar',
            'meteor_shower',
            'conjunction',
            'planetary_event',
        ], true)) {
            $score += 50;
        }

        $source = Str::lower((string) ($event->source_name ?? ''));
        if (in_array($source, ['manual', 'curated'], true)) {
            $score += 20;
        }

        if ($event->end_at !== null) {
            $score += 5;
        }

        if ($eventMoment) {
            $eventLocal = $eventMoment->setTimezone($this->timezone());
            $hoursFromMonthStart = max(0, $monthStartLocal->diffInHours($eventLocal));
            $soonness = max(0, 10 - intdiv(min($hoursFromMonthStart, 24 * 30), 24 * 3));
            $score += $soonness;
        }

        return $score;
    }

    private function publishedMonthEventsQuery(CarbonImmutable $monthStartUtc, CarbonImmutable $monthEndUtc): Builder
    {
        return $this->publishedEventQuery->base()
            ->where(function (Builder $query) use ($monthStartUtc, $monthEndUtc): void {
                $query->whereBetween('start_at', [$monthStartUtc, $monthEndUtc])
                    ->orWhere(function (Builder $sub) use ($monthStartUtc, $monthEndUtc): void {
                        $sub->whereNull('start_at')
                            ->whereBetween('max_at', [$monthStartUtc, $monthEndUtc]);
                    });
            });
    }

    /**
     * @return array{0:CarbonImmutable,1:CarbonImmutable,2:CarbonImmutable}
     */
    private function monthBoundaries(string $monthKey): array
    {
        $monthStartLocal = CarbonImmutable::createFromFormat('Y-m', $monthKey, $this->timezone())
            ->startOfMonth()
            ->startOfDay();
        $monthEndLocal = $monthStartLocal->endOfMonth()->endOfDay();

        return [
            $monthStartLocal->utc(),
            $monthEndLocal->utc(),
            $monthStartLocal,
        ];
    }

    private function shouldIncludeLegacyRows(string $monthKey): bool
    {
        return $monthKey === now($this->timezone())->format('Y-m');
    }

    private function timezone(): string
    {
        return (string) config('events.timezone', config('app.timezone', 'UTC'));
    }

    private function fallbackCacheKey(string $monthKey, int $limit): string
    {
        return sprintf('featured_fallback:%s:%d', $monthKey, $limit);
    }

    /**
     * @return array<string,mixed>|null
     */
    private function mapEvent(?Event $event): ?array
    {
        if (! $event) {
            return null;
        }

        $googleCalendarUrl = $this->calendarLinks->googleCalendarUrl($event);
        $icsUrl = $this->calendarLinks->eventIcsUrl($event);

        return [
            'id' => (int) $event->id,
            'title' => (string) $event->title,
            'slug' => Str::slug((string) $event->title),
            'start_at' => optional($event->start_at)->toIso8601String(),
            'end_at' => optional($event->end_at)->toIso8601String(),
            'calendar' => [
                'google_calendar_url' => $googleCalendarUrl,
                'ics_url' => $icsUrl,
            ],
            'google_calendar_url' => $googleCalendarUrl,
            'ics_url' => $icsUrl,
        ];
    }

    private function eventMoment(Event $event): ?CarbonImmutable
    {
        if ($event->start_at !== null) {
            return CarbonImmutable::instance($event->start_at);
        }

        if ($event->max_at !== null) {
            return CarbonImmutable::instance($event->max_at);
        }

        return null;
    }
}
