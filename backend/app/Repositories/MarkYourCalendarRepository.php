<?php

namespace App\Repositories;

use App\Models\Event;
use App\Models\MonthlyFeaturedEvent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class MarkYourCalendarRepository
{
    private ?bool $hasMonthlyFeaturedMonthKeyColumn = null;
    private ?bool $hasEventsPriorityColumn = null;
    private ?bool $hasEventsStartDateColumn = null;

    /**
     * @return array<int, Event>
     */
    public function adminFeaturedEvents(string $monthKey, Carbon $now, int $limit): array
    {
        $safeLimit = max(1, $limit);

        $query = MonthlyFeaturedEvent::query()
            ->where('is_active', true)
            ->with([
                'event:id,title,start_at,end_at,max_at,description,short,visibility,source_name,source_uid',
            ]);

        if ($this->supportsMonthlyFeaturedMonthKeyColumn()) {
            $query->where(function (Builder $builder) use ($monthKey): void {
                $builder->where('month_key', $monthKey);

                if ($this->shouldIncludeLegacyRows($monthKey)) {
                    $builder->orWhereNull('month_key');
                }
            });
        }

        return $query
            ->orderBy('position')
            ->orderBy('id')
            ->limit($safeLimit)
            ->get()
            ->map(static fn (MonthlyFeaturedEvent $item): ?Event => $item->event)
            ->filter(fn (?Event $event): bool => $this->isEventEligibleForPopup($event, $now))
            ->values()
            ->all();
    }

    /**
     * @return array<int, Event>
     */
    public function fallbackEventsForCurrentMonth(Carbon $now, int $limit): array
    {
        $safeLimit = max(1, min($limit, 10));
        $monthStart = $now->copy()->setTimezone($this->timezone())->startOfMonth()->startOfDay()->utc();
        $monthEnd = $now->copy()->setTimezone($this->timezone())->endOfMonth()->endOfDay()->utc();
        $query = Event::query()
            ->where('visibility', 1)
            ->published()
            ->whereNotNull('start_at')
            ->where('start_at', '>=', $now->copy()->utc())
            ->whereBetween('start_at', [$monthStart, $monthEnd]);

        if ($this->supportsEventsPriorityColumn()) {
            $query->orderByDesc('priority');
        }

        if ($this->supportsEventsStartDateColumn()) {
            $query->orderBy('start_date');
        } else {
            $query->orderBy('start_at');
        }

        return $query
            ->orderBy('id')
            ->limit($safeLimit)
            ->get(['id', 'title', 'start_at', 'end_at', 'max_at', 'description', 'short', 'visibility', 'source_name', 'source_uid'])
            ->filter(fn (Event $event): bool => $this->isEventEligibleForPopup($event, $now))
            ->values()
            ->all();
    }

    public function supportsMonthlyFeaturedMonthKeyColumn(): bool
    {
        if ($this->hasMonthlyFeaturedMonthKeyColumn !== null) {
            return $this->hasMonthlyFeaturedMonthKeyColumn;
        }

        $this->hasMonthlyFeaturedMonthKeyColumn = Schema::hasColumn('monthly_featured_events', 'month_key');

        return $this->hasMonthlyFeaturedMonthKeyColumn;
    }

    private function supportsEventsPriorityColumn(): bool
    {
        if ($this->hasEventsPriorityColumn !== null) {
            return $this->hasEventsPriorityColumn;
        }

        $this->hasEventsPriorityColumn = Schema::hasColumn('events', 'priority');

        return $this->hasEventsPriorityColumn;
    }

    private function supportsEventsStartDateColumn(): bool
    {
        if ($this->hasEventsStartDateColumn !== null) {
            return $this->hasEventsStartDateColumn;
        }

        $this->hasEventsStartDateColumn = Schema::hasColumn('events', 'start_date');

        return $this->hasEventsStartDateColumn;
    }

    private function shouldIncludeLegacyRows(string $monthKey): bool
    {
        return $monthKey === now($this->timezone())->format('Y-m');
    }

    private function timezone(): string
    {
        return (string) config('events.timezone', config('app.timezone', 'UTC'));
    }

    private function isEventEligibleForPopup(?Event $event, Carbon $now): bool
    {
        if (! $event) {
            return false;
        }

        if ((int) $event->visibility !== 1) {
            return false;
        }

        $published = filled($event->source_name) && filled($event->source_uid);
        if (! $published) {
            return false;
        }

        $startAt = $event->start_at;
        if (! $startAt instanceof Carbon) {
            return false;
        }

        return $startAt->greaterThanOrEqualTo($now);
    }
}
