<?php

namespace App\Services\Newsletter;

use App\Models\BlogPost;
use App\Models\Event;
use App\Models\NewsletterFeaturedEvent;
use App\Models\NewsletterRun;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class NewsletterSelectionService
{
    public const MAX_FEATURED_EVENTS = 10;

    /**
     * @return array{start: CarbonImmutable, end: CarbonImmutable, week_start_date: string}
     */
    public function getNextWeekRange(): array
    {
        $timezone = (string) config('events.timezone', config('app.timezone', 'UTC'));
        $start = CarbonImmutable::now($timezone)
            ->startOfWeek(CarbonImmutable::MONDAY)
            ->addWeek()
            ->startOfDay();
        $end = $start->addDays(6)->endOfDay();

        return [
            'start' => $start,
            'end' => $end,
            'week_start_date' => $start->toDateString(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getAdminSelectedEvents(?NewsletterRun $run = null): array
    {
        $range = $this->getNextWeekRange();
        $start = $range['start'];
        $end = $range['end'];

        $query = NewsletterFeaturedEvent::query()
            ->with(['event:id,title,start_at,end_at'])
            ->orderBy('order')
            ->orderBy('id')
            ->limit(self::MAX_FEATURED_EVENTS);

        if ($run) {
            $query->where('newsletter_run_id', $run->id);
        } else {
            $query
                ->whereNull('newsletter_run_id')
                ->whereHas('event', function ($builder) use ($start, $end): void {
                    $builder->whereBetween('start_at', [$start, $end]);
                });
        }

        return $query->get()
            ->map(function (NewsletterFeaturedEvent $item): ?array {
                $event = $item->event;
                if (! $event) {
                    return null;
                }

                return [
                    'id' => (int) $event->id,
                    'title' => (string) $event->title,
                    'start_at' => optional($event->start_at)->toIso8601String(),
                    'end_at' => optional($event->end_at)->toIso8601String(),
                    'url' => $this->frontendUrl('/events/' . $event->id),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param array<int, int|string> $eventIds
     * @return array<int, array<string, mixed>>
     */
    public function replaceAdminSelectedEvents(array $eventIds): array
    {
        $normalizedIds = array_values(array_unique(array_map(
            static fn ($value): int => (int) $value,
            array_filter($eventIds, static fn ($value): bool => (int) $value > 0)
        )));

        if (count($normalizedIds) > self::MAX_FEATURED_EVENTS) {
            throw ValidationException::withMessages([
                'event_ids' => ['Maximum 10 featured events are allowed.'],
            ]);
        }

        $range = $this->getNextWeekRange();
        $start = $range['start'];
        $end = $range['end'];

        $eventsById = Event::query()
            ->published()
            ->whereIn('id', $normalizedIds)
            ->whereBetween('start_at', [$start, $end])
            ->get(['id'])
            ->keyBy('id');

        $missing = array_values(array_filter($normalizedIds, static fn (int $id): bool => ! $eventsById->has($id)));
        if ($missing !== []) {
            throw ValidationException::withMessages([
                'event_ids' => ['Some selected events are unavailable for next week: ' . implode(', ', $missing)],
            ]);
        }

        DB::transaction(function () use ($normalizedIds): void {
            NewsletterFeaturedEvent::query()->whereNull('newsletter_run_id')->delete();

            if ($normalizedIds === []) {
                return;
            }

            $timestamp = now();
            $rows = [];
            foreach ($normalizedIds as $index => $eventId) {
                $rows[] = [
                    'newsletter_run_id' => null,
                    'event_id' => $eventId,
                    'order' => $index,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];
            }

            NewsletterFeaturedEvent::query()->insert($rows);
        });

        return $this->getAdminSelectedEvents();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getTopArticlesLast7Days(int $limit = 4): array
    {
        $safeLimit = max(1, min($limit, 6));
        $from = now()->subDays(7);

        return BlogPost::query()
            ->published()
            ->where('published_at', '>=', $from)
            ->orderByDesc('views')
            ->orderByDesc('published_at')
            ->limit($safeLimit)
            ->get(['id', 'title', 'slug', 'views', 'published_at'])
            ->map(function (BlogPost $post): array {
                $slugOrId = $post->slug ?: (string) $post->id;
                return [
                    'id' => (int) $post->id,
                    'title' => (string) $post->title,
                    'slug' => (string) $post->slug,
                    'views' => (int) $post->views,
                    'published_at' => optional($post->published_at)->toIso8601String(),
                    'url' => $this->frontendUrl('/learn/' . $slugOrId),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function buildNewsletterPayload(): array
    {
        $range = $this->getNextWeekRange();
        $events = $this->getAdminSelectedEvents();

        if ($events === []) {
            $events = $this->fallbackUpcomingEvents(3);
        }

        $topArticlesLimit = max(1, (int) config('newsletter.top_articles_limit', 4));
        $articles = $this->getTopArticlesLast7Days($topArticlesLimit);

        try {
            $tip = $this->buildAstronomicalTip($events);
        } catch (\Throwable) {
            $tip = $this->fallbackAstronomicalTip();
        }

        return [
            'week' => [
                'start' => $range['start']->toDateString(),
                'end' => $range['end']->toDateString(),
                'start_iso' => $range['start']->toIso8601String(),
                'end_iso' => $range['end']->toIso8601String(),
            ],
            'top_events' => $events,
            'top_articles' => $articles,
            'astronomical_tip' => $tip,
            'cta' => [
                'calendar_url' => $this->frontendUrl('/calendar'),
                'events_url' => $this->frontendUrl('/events'),
                'article_urls' => array_values(array_map(
                    static fn (array $article): string => (string) ($article['url'] ?? ''),
                    $articles
                )),
            ],
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fallbackUpcomingEvents(int $limit): array
    {
        $range = $this->getNextWeekRange();
        $start = $range['start'];
        $end = $range['end'];
        $safeLimit = max(1, min($limit, self::MAX_FEATURED_EVENTS));

        return Event::query()
            ->published()
            ->whereBetween('start_at', [$start, $end])
            ->orderBy('start_at')
            ->limit($safeLimit)
            ->get(['id', 'title', 'start_at', 'end_at'])
            ->map(function (Event $event): array {
                return [
                    'id' => (int) $event->id,
                    'title' => (string) $event->title,
                    'start_at' => optional($event->start_at)->toIso8601String(),
                    'end_at' => optional($event->end_at)->toIso8601String(),
                    'url' => $this->frontendUrl('/events/' . $event->id),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param array<int, array<string, mixed>> $events
     */
    private function buildAstronomicalTip(array $events): string
    {
        if ($events === []) {
            return $this->fallbackAstronomicalTip();
        }

        $first = $events[0];
        $title = trim((string) ($first['title'] ?? 'vybranej udalosti'));
        $title = Str::limit($title, 100, '...');
        $startRaw = (string) ($first['start_at'] ?? '');
        $timezone = (string) config('events.timezone', config('app.timezone', 'UTC'));

        $startLabel = 'buduci tyzden';
        if ($startRaw !== '') {
            try {
                $startLabel = CarbonImmutable::parse($startRaw)->setTimezone($timezone)->format('d.m.Y H:i');
            } catch (\Throwable) {
                $startLabel = 'buduci tyzden';
            }
        }

        return "Astronomicky tip tyzdna: Pri udalosti \"{$title}\" okolo {$startLabel} vyhladajte tmavsie miesto mimo mesta, nechajte oci adaptovat sa aspon 20 minut a pripravte si plan B pre pripad oblakov.";
    }

    private function fallbackAstronomicalTip(): string
    {
        $tips = config('newsletter.fallback_tips', []);
        if (! is_array($tips) || $tips === []) {
            return 'Astronomicky tip tyzdna: Sledujte predpoved pocasia, vyberte si tmavsiu lokalitu a vezmite si jednoduchu mapu oblohy alebo aplikaciu na orientaciu.';
        }

        $seed = (int) CarbonImmutable::now()->format('W');
        $index = $seed % count($tips);
        $candidate = trim((string) ($tips[$index] ?? ''));

        return $candidate !== ''
            ? $candidate
            : 'Astronomicky tip tyzdna: Sledujte predpoved pocasia, vyberte si tmavsiu lokalitu a vezmite si jednoduchu mapu oblohy alebo aplikaciu na orientaciu.';
    }

    private function frontendUrl(string $path): string
    {
        $base = rtrim((string) config('newsletter.frontend_base_url', config('app.url')), '/');
        return $base . '/' . ltrim($path, '/');
    }
}
