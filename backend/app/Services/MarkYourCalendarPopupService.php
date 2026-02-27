<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Event;
use App\Models\MonthlyFeaturedEvent;
use App\Models\User;
use App\Repositories\MarkYourCalendarRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MarkYourCalendarPopupService
{
    public const SETTINGS_ENABLED_KEY = 'calendar_popup_enabled';
    public const SETTINGS_FORCE_VERSION_KEY = 'calendar_popup_force_version';
    public const SETTINGS_FORCE_AT_KEY = 'calendar_popup_force_at';
    public const MAX_ACTIVE_ITEMS = 10;
    public const MAX_ROWS = 2;
    public const FALLBACK_ITEMS = 3;

    public function __construct(
        private readonly FeaturedEventsResolver $featuredResolver,
        private readonly EventCalendarLinksService $calendarLinks,
        private readonly MarkYourCalendarRepository $popupRepository,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function payloadForUser(User $user): array
    {
        $now = now();
        $enabled = $this->isEnabled();
        $monthKey = $this->monthKey($now);
        $forceVersion = $this->forceVersion();

        try {
            $resolved = $this->resolvePopupEvents($monthKey, $now);
        } catch (\Throwable $exception) {
            Log::error('Failed to resolve mark-your-calendar popup payload.', [
                'user_id' => $user->id,
                'month_key' => $monthKey,
                'message' => $exception->getMessage(),
            ]);

            $resolved = [
                'mode' => 'empty',
                'fallback_reason' => 'resolution_error',
                'events' => [],
            ];
        }

        $items = $resolved['events'];
        $reason = 'already_seen';
        $shouldShow = false;

        if (! $enabled) {
            $reason = 'disabled';
        } elseif ($items === []) {
            $reason = 'empty';
        } elseif ($forceVersion > (int) $user->calendar_popup_last_force_version) {
            $shouldShow = true;
            $reason = 'forced';
        } else {
            $lastSeen = $user->last_calendar_popup_at;
            $lastSeenMonthKey = $lastSeen ? $this->monthKey($lastSeen) : null;

            if ($lastSeenMonthKey !== $monthKey) {
                $shouldShow = true;
                $reason = 'monthly';
            }
        }

        return [
            'mode' => $resolved['mode'],
            'events' => $items,
            'should_show' => $shouldShow,
            'reason' => $reason,
            'force_version' => $forceVersion,
            'month_key' => $monthKey,
            'selection_mode' => $resolved['mode'],
            'fallback_reason' => $resolved['fallback_reason'],
            'items' => $items,
            'calendar' => [
                'bundle_ics_url' => $this->calendarLinks->featuredBundleIcsUrl($monthKey),
            ],
            'meta' => [
                'max_items' => self::MAX_ACTIVE_ITEMS,
                'max_rows' => self::MAX_ROWS,
            ],
            'generated_at' => $now->toIso8601String(),
        ];
    }

    public function acknowledgeSeen(User $user, int $forceVersion): void
    {
        $user->forceFill([
            'last_calendar_popup_at' => now(),
            'calendar_popup_last_force_version' => max((int) $user->calendar_popup_last_force_version, $forceVersion),
        ])->save();
    }

    /**
     * @return array<string,mixed>
     */
    public function adminOverview(?string $monthKey = null, bool $refreshFallback = false): array
    {
        $resolvedMonth = $this->resolveMonthKey($monthKey);
        $resolved = $this->featuredResolver->resolveForMonth($resolvedMonth, FeaturedEventsResolver::DEFAULT_FALLBACK_LIMIT, false);
        $fallbackPreview = $this->featuredResolver->fallbackPreviewForMonth(
            $resolvedMonth,
            FeaturedEventsResolver::DEFAULT_FALLBACK_LIMIT,
            $refreshFallback
        );

        return [
            'month' => $resolvedMonth,
            'selection_mode' => $resolved['mode'],
            'fallback_reason' => $resolved['fallback_reason'],
            'data' => $this->adminFeaturedEvents($resolvedMonth),
            'fallback_preview' => $fallbackPreview['events'],
            'resolved_events' => $resolved['events'],
            'calendar' => [
                'bundle_ics_url' => $this->calendarLinks->featuredBundleIcsUrl($resolvedMonth),
            ],
            'settings' => $this->settingsPayload(),
            'meta' => [
                'max_items' => self::MAX_ACTIVE_ITEMS,
                'fallback_items' => FeaturedEventsResolver::DEFAULT_FALLBACK_LIMIT,
            ],
        ];
    }

    /**
     * @return array<int, array<string,mixed>>
     */
    public function adminFeaturedEvents(?string $monthKey = null): array
    {
        $resolvedMonth = $this->resolveMonthKey($monthKey);

        return $this->monthSelectionQuery($resolvedMonth)
            ->with('event:id,title,start_at,end_at')
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->map(function (MonthlyFeaturedEvent $item): array {
                return [
                    'id' => $item->id,
                    'event_id' => $item->event_id,
                    'month_key' => $item->month_key,
                    'position' => (int) $item->position,
                    'is_active' => (bool) $item->is_active,
                    'event' => $item->event ? [
                        'id' => $item->event->id,
                        'title' => $item->event->title,
                        'start_at' => optional($item->event->start_at)->toIso8601String(),
                        'end_at' => optional($item->event->end_at)->toIso8601String(),
                    ] : null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string,mixed>
     */
    public function createFeaturedEvent(User $admin, int $eventId, ?int $position, ?string $monthKey = null): array
    {
        $resolvedMonth = $this->resolveMonthKey($monthKey);
        $supportsMonthKey = $this->popupRepository->supportsMonthlyFeaturedMonthKeyColumn();

        Event::query()->findOrFail($eventId);

        if ($this->monthSelectionQuery($resolvedMonth)->where('event_id', $eventId)->exists()) {
            throw ValidationException::withMessages([
                'event_id' => ['Event is already selected for this month.'],
            ]);
        }

        if ($this->activeCount($resolvedMonth) >= self::MAX_ACTIVE_ITEMS) {
            throw ValidationException::withMessages([
                'event_id' => ['Maximum 10 active featured events are allowed.'],
            ]);
        }

        $item = DB::transaction(function () use ($admin, $eventId, $position, $resolvedMonth, $supportsMonthKey): MonthlyFeaturedEvent {
            $attributes = [
                'event_id' => $eventId,
                'position' => $this->nextPosition($resolvedMonth),
                'is_active' => true,
                'created_by' => $admin->id,
            ];

            if ($supportsMonthKey) {
                $attributes['month_key'] = $resolvedMonth;
            }

            $item = MonthlyFeaturedEvent::query()->create($attributes);

            if ($position !== null) {
                $item->position = max(0, $position);
                $item->save();
            }

            $this->normalizePositions($resolvedMonth);

            return $item->fresh(['event:id,title,start_at,end_at']);
        });

        $this->forgetMonthCaches($resolvedMonth);

        return $this->mapAdminItem($item);
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function updateFeaturedEvent(MonthlyFeaturedEvent $item, array $payload): array
    {
        $resolvedMonth = $item->month_key ?: $this->resolveMonthKey(null);

        $targetIsActive = array_key_exists('is_active', $payload)
            ? (bool) $payload['is_active']
            : (bool) $item->is_active;

        if ($targetIsActive && ! $item->is_active && $this->activeCount($resolvedMonth) >= self::MAX_ACTIVE_ITEMS) {
            throw ValidationException::withMessages([
                'is_active' => ['Maximum 10 active featured events are allowed.'],
            ]);
        }

        $updated = DB::transaction(function () use ($item, $payload, $resolvedMonth): MonthlyFeaturedEvent {
            if (array_key_exists('position', $payload)) {
                $item->position = max(0, (int) $payload['position']);
            }

            if (array_key_exists('is_active', $payload)) {
                $item->is_active = (bool) $payload['is_active'];
            }

            $item->save();
            $this->normalizePositions($resolvedMonth);

            return $item->fresh(['event:id,title,start_at,end_at']);
        });

        $this->forgetMonthCaches($resolvedMonth);

        return $this->mapAdminItem($updated);
    }

    public function deleteFeaturedEvent(MonthlyFeaturedEvent $item): void
    {
        $resolvedMonth = $item->month_key ?: $this->resolveMonthKey(null);

        DB::transaction(function () use ($item, $resolvedMonth): void {
            $item->delete();
            $this->normalizePositions($resolvedMonth);
        });

        $this->forgetMonthCaches($resolvedMonth);
    }

    /**
     * @return array<string,mixed>
     */
    public function applyFallbackAsFeatured(User $admin, ?string $monthKey = null): array
    {
        $resolvedMonth = $this->resolveMonthKey($monthKey);
        $supportsMonthKey = $this->popupRepository->supportsMonthlyFeaturedMonthKeyColumn();
        $fallback = $this->featuredResolver->fallbackPreviewForMonth(
            $resolvedMonth,
            FeaturedEventsResolver::DEFAULT_FALLBACK_LIMIT,
            true
        );

        DB::transaction(function () use ($resolvedMonth, $fallback, $admin, $supportsMonthKey): void {
            $this->monthSelectionQuery($resolvedMonth)->delete();

            $rows = [];
            $timestamp = now();

            foreach ($fallback['events'] as $index => $event) {
                $eventId = (int) ($event['id'] ?? 0);
                if ($eventId <= 0) {
                    continue;
                }

                $row = [
                    'event_id' => $eventId,
                    'position' => $index,
                    'is_active' => true,
                    'created_by' => $admin->id,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];

                if ($supportsMonthKey) {
                    $row['month_key'] = $resolvedMonth;
                }

                $rows[] = $row;
            }

            if ($rows !== []) {
                MonthlyFeaturedEvent::query()->insert($rows);
            }

            $this->normalizePositions($resolvedMonth);
        });

        $this->forgetMonthCaches($resolvedMonth);

        return [
            'month' => $resolvedMonth,
            'applied_count' => count($fallback['events']),
            'data' => $this->adminFeaturedEvents($resolvedMonth),
        ];
    }

    /**
     * @return array{enabled: bool, force_version: int, force_at: ?string}
     */
    public function settingsPayload(): array
    {
        return [
            'enabled' => $this->isEnabled(),
            'force_version' => $this->forceVersion(),
            'force_at' => $this->forceAt(),
        ];
    }

    /**
     * @return array{enabled: bool, force_version: int, force_at: ?string}
     */
    public function updateEnabled(bool $enabled): array
    {
        AppSetting::put(self::SETTINGS_ENABLED_KEY, $enabled ? '1' : '0');

        return $this->settingsPayload();
    }

    /**
     * @return array{force_version: int, force_at: string}
     */
    public function forceShowNow(): array
    {
        $nextVersion = $this->forceVersion() + 1;
        $forcedAt = now()->toIso8601String();

        AppSetting::put(self::SETTINGS_FORCE_VERSION_KEY, $nextVersion);
        AppSetting::put(self::SETTINGS_FORCE_AT_KEY, $forcedAt);

        return [
            'force_version' => $nextVersion,
            'force_at' => $forcedAt,
        ];
    }

    public function resolveMonthKey(?string $monthKey): string
    {
        if (! is_string($monthKey) || trim($monthKey) === '') {
            return $this->monthKey(now());
        }

        $normalized = trim($monthKey);
        if (! preg_match('/^\d{4}-\d{2}$/', $normalized)) {
            throw ValidationException::withMessages([
                'month' => ['Month must be in YYYY-MM format.'],
            ]);
        }

        try {
            Carbon::createFromFormat('Y-m', $normalized, config('app.timezone', 'UTC'))->startOfMonth();
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'month' => ['Month must be a valid calendar month.'],
            ]);
        }

        return $normalized;
    }

    /**
     * @return array{mode:'admin'|'fallback'|'empty',fallback_reason:?string,events:array<int,array<string,mixed>>}
     */
    private function resolvePopupEvents(string $monthKey, Carbon $now): array
    {
        $adminEvents = $this->popupRepository->adminFeaturedEvents($monthKey, $now, self::MAX_ACTIVE_ITEMS);
        $mappedAdminEvents = $this->mapPopupEvents($adminEvents, $monthKey, 'admin');
        if ($mappedAdminEvents !== []) {
            return [
                'mode' => 'admin',
                'fallback_reason' => null,
                'events' => $mappedAdminEvents,
            ];
        }

        $fallbackEvents = $this->popupRepository->fallbackEventsForCurrentMonth($now, self::FALLBACK_ITEMS);
        $mappedFallbackEvents = $this->mapPopupEvents($fallbackEvents, $monthKey, 'fallback');
        if ($mappedFallbackEvents !== []) {
            return [
                'mode' => 'fallback',
                'fallback_reason' => 'no_admin_selection',
                'events' => $mappedFallbackEvents,
            ];
        }

        return [
            'mode' => 'empty',
            'fallback_reason' => 'no_events_in_month',
            'events' => [],
        ];
    }

    /**
     * @param array<int,Event> $events
     * @return array<int,array<string,mixed>>
     */
    private function mapPopupEvents(array $events, string $monthKey, string $source): array
    {
        return collect($events)
            ->map(function (Event $event) use ($monthKey, $source): ?array {
                try {
                    $startAt = optional($event->start_at)->toIso8601String();
                    if (! $startAt) {
                        return null;
                    }

                    $googleCalendarUrl = $this->calendarLinks->googleCalendarUrl($event);
                    $icsUrl = $this->calendarLinks->eventIcsUrl($event);

                    return [
                        'id' => (int) $event->id,
                        'title' => (string) $event->title,
                        'slug' => Str::slug((string) $event->title),
                        'start_at' => $startAt,
                        'end_at' => optional($event->end_at)->toIso8601String(),
                        'calendar' => [
                            'google_calendar_url' => $googleCalendarUrl,
                            'ics_url' => $icsUrl,
                        ],
                        'google_calendar_url' => $googleCalendarUrl,
                        'ics_url' => $icsUrl,
                    ];
                } catch (\Throwable $exception) {
                    Log::warning('Skipping popup event due to invalid event payload.', [
                        'event_id' => optional($event)->id,
                        'month_key' => $monthKey,
                        'source' => $source,
                        'message' => $exception->getMessage(),
                    ]);

                    return null;
                }
            })
            ->filter()
            ->values()
            ->all();
    }

    private function isEnabled(): bool
    {
        return AppSetting::getBool(self::SETTINGS_ENABLED_KEY, true);
    }

    private function forceVersion(): int
    {
        return AppSetting::getInt(self::SETTINGS_FORCE_VERSION_KEY, 0);
    }

    private function forceAt(): ?string
    {
        $raw = AppSetting::getString(self::SETTINGS_FORCE_AT_KEY);
        if (! $raw) {
            return null;
        }

        try {
            return Carbon::parse($raw)->toIso8601String();
        } catch (\Throwable) {
            return null;
        }
    }

    private function monthKey(Carbon $value): string
    {
        return $value->copy()->setTimezone(config('app.timezone'))->format('Y-m');
    }

    private function monthSelectionQuery(string $monthKey): Builder
    {
        $query = MonthlyFeaturedEvent::query();

        if (! $this->popupRepository->supportsMonthlyFeaturedMonthKeyColumn()) {
            return $query;
        }

        return $query->where(function (Builder $builder) use ($monthKey): void {
            $builder->where('month_key', $monthKey);

            if ($this->includeLegacyRows($monthKey)) {
                $builder->orWhereNull('month_key');
            }
        });
    }

    private function includeLegacyRows(string $monthKey): bool
    {
        return $monthKey === $this->monthKey(now());
    }

    private function activeCount(string $monthKey): int
    {
        return $this->monthSelectionQuery($monthKey)
            ->where('is_active', true)
            ->count();
    }

    private function nextPosition(string $monthKey): int
    {
        return (int) $this->monthSelectionQuery($monthKey)->max('position') + 1;
    }

    private function normalizePositions(string $monthKey): void
    {
        $rows = $this->monthSelectionQuery($monthKey)
            ->orderBy('position')
            ->orderBy('id')
            ->get(['id']);

        foreach ($rows as $index => $row) {
            MonthlyFeaturedEvent::query()
                ->whereKey($row->id)
                ->update(['position' => $index]);
        }
    }

    private function forgetMonthCaches(string $monthKey): void
    {
        $this->featuredResolver->forgetFallbackCache($monthKey);
    }

    /**
     * @return array<string,mixed>
     */
    private function mapAdminItem(MonthlyFeaturedEvent $item): array
    {
        return [
            'id' => $item->id,
            'event_id' => $item->event_id,
            'month_key' => $item->month_key,
            'position' => (int) $item->position,
            'is_active' => (bool) $item->is_active,
            'event' => $item->event ? [
                'id' => $item->event->id,
                'title' => $item->event->title,
                'start_at' => optional($item->event->start_at)->toIso8601String(),
                'end_at' => optional($item->event->end_at)->toIso8601String(),
            ] : null,
        ];
    }
}
