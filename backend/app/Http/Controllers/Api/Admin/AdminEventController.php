<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\EventType;
use App\Enums\RegionScope;
use App\Http\Controllers\Controller;
use App\Http\Resources\EventResource;
use App\Models\Event;
use Carbon\CarbonImmutable;
use App\Support\EventTime;
use App\Services\Events\EventFeedRealtimePublisher;
use App\Services\Events\EventDescriptionOriginRecorder;
use App\Services\Events\EventDescriptionReviewGateService;
use App\Services\Events\PublishedEventQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminEventController extends Controller
{
    private const BULK_DELETE_CONFIRM_TOKEN = 'delete_events';

    public function __construct(
        private readonly EventFeedRealtimePublisher $eventFeedRealtimePublisher,
        private readonly EventDescriptionOriginRecorder $originRecorder,
        private readonly EventDescriptionReviewGateService $reviewGate,
        private readonly PublishedEventQuery $publishedEventQuery,
    ) {
    }

    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 20);
        if ($perPage < 1) {
            $perPage = 1;
        }
        if ($perPage > 50) {
            $perPage = 50;
        }

        $scope = strtolower(trim((string) $request->query('scope', '')));
        $eventsQuery = $scope === 'published'
            ? $this->publishedEventQuery->base()
            : Event::query();
        $this->applyEventFilters($eventsQuery, [
            'search' => $request->query('search'),
            'type' => $request->query('type'),
            'visibility' => $request->query('visibility'),
            'year' => $request->query('year'),
            'month' => $request->query('month'),
            'day' => $request->query('day'),
            'source_kind' => $request->query('source_kind'),
            'source_name' => $request->query('source_name'),
        ]);

        $events = $eventsQuery
            ->orderByRaw('COALESCE(start_at, max_at) DESC')
            ->paginate($perPage);

        return EventResource::collection($events);
    }

    public function show(Event $event)
    {
        return new EventResource($event);
    }

    public function store(Request $request)
    {
        $validated = $this->validateEvent($request);

        $event = new Event();
        $event->title = $validated['title'];
        $event->description = $validated['description'] ?? null;
        $event->short = $validated['short'] ?? null;
        $event->type = $validated['type'];
        $event->icon_emoji = $this->normalizeIconEmoji($validated['icon_emoji'] ?? null);
        $event->region_scope = $validated['region_scope'] ?? RegionScope::Global->value;
        $event->start_at = $validated['start_at'];
        $event->end_at = $validated['end_at'] ?? null;
        $event->visibility = $validated['visibility'];
        $event->source_name = 'manual';
        $event->source_uid = (string) Str::uuid();
        $event->max_at = $event->start_at;
        $event->time_type = EventTime::TYPE_START;
        $event->time_precision = EventTime::PRECISION_EXACT;
        $event->save();
        $this->originRecorder->record(
            event: $event,
            source: 'admin_event_store',
            sourceDetail: 'manual_form'
        );
        $this->eventFeedRealtimePublisher->publish($event);

        return new EventResource($event);
    }

    public function update(Request $request, Event $event)
    {
        $previousDescription = trim((string) ($event->description ?? ''));
        $previousShort = trim((string) ($event->short ?? ''));
        $previousVisibility = (int) ($event->visibility ?? 0);

        $validated = $this->validateEvent($request);
        $nextVisibility = (int) ($validated['visibility'] ?? 0);
        $isPublishTransition = $previousVisibility !== 1 && $nextVisibility === 1;
        $forcePublish = (bool) $request->boolean('force_publish');

        if ($isPublishTransition && ! $forcePublish) {
            $gate = $this->reviewGate->evaluateForPublish($event);
            if ((bool) ($gate['required'] ?? false)) {
                return response()->json([
                    'message' => (string) ($gate['message'] ?? 'Udalost vyzaduje kontrolu opisu pred zverejnenim.'),
                    'error_code' => 'AI_DESCRIPTION_REVIEW_REQUIRED',
                    'action' => 'REVIEW_EVENT_DESCRIPTION',
                    'review_gate' => [
                        'reason_code' => $gate['reason_code'] ?? null,
                        'source' => $gate['source'] ?? null,
                        'source_detail' => $gate['source_detail'] ?? null,
                    ],
                ], 422);
            }
        }

        $event->title = $validated['title'];
        $event->description = $validated['description'] ?? null;
        if (array_key_exists('short', $validated)) {
            $event->short = $validated['short'] !== null ? trim((string) $validated['short']) : null;
        }
        $event->type = $validated['type'];
        $event->icon_emoji = $this->normalizeIconEmoji($validated['icon_emoji'] ?? null);
        $event->region_scope = $validated['region_scope'] ?? $event->region_scope ?? RegionScope::Global->value;
        $event->start_at = $validated['start_at'];
        $event->end_at = $validated['end_at'] ?? null;
        $event->visibility = $validated['visibility'];
        $event->max_at = $event->start_at;
        $event->time_type = EventTime::TYPE_START;
        $event->time_precision = EventTime::PRECISION_EXACT;
        $event->save();

        $currentDescription = trim((string) ($event->description ?? ''));
        $currentShort = trim((string) ($event->short ?? ''));
        if ($currentDescription !== $previousDescription || $currentShort !== $previousShort) {
            $this->originRecorder->record(
                event: $event,
                source: 'admin_event_update',
                sourceDetail: 'manual_form'
            );
        }

        return new EventResource($event);
    }

    public function destroy(Request $request, Event $event): JsonResponse
    {
        $eventId = (int) $event->id;
        $eventTitle = (string) $event->title;
        $eventSource = is_string($event->source_name) ? $event->source_name : null;

        $event->delete();

        Log::info('Admin deleted event.', [
            'admin_id' => $request->user()?->id,
            'event_id' => $eventId,
            'source_name' => $eventSource,
        ]);

        return response()->json([
            'deleted' => true,
            'id' => $eventId,
            'title' => $eventTitle,
            'source_name' => $eventSource,
        ]);
    }

    public function bulkDestroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'scope' => ['required', 'string', Rule::in(['all', 'filtered'])],
            'dry_run' => ['sometimes', 'boolean'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:5000'],
            'confirm_token' => ['nullable', 'string'],
            'filters' => ['nullable', 'array'],
            'filters.search' => ['nullable', 'string', 'max:255'],
            'filters.type' => ['nullable', 'string', Rule::in(EventType::values())],
            'filters.visibility' => ['nullable', 'integer', Rule::in([0, 1])],
            'filters.year' => ['nullable', 'integer', 'min:1900', 'max:2200'],
            'filters.month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'filters.day' => ['nullable', 'integer', 'min:1', 'max:31'],
            'filters.source_kind' => ['nullable', 'string', Rule::in(['manual', 'crawled'])],
            'filters.source_name' => ['nullable', 'string', 'max:120'],
        ]);

        $scope = (string) $validated['scope'];
        $dryRun = (bool) ($validated['dry_run'] ?? false);
        $limit = isset($validated['limit']) ? (int) $validated['limit'] : null;
        $filters = $this->normalizeFilterPayload((array) ($validated['filters'] ?? []));

        if ($scope === 'filtered' && ! $this->hasAnyFilterValue($filters)) {
            return response()->json([
                'message' => 'Pre rezim filtered musis zadat aspon jeden filter.',
            ], 422);
        }

        $query = Event::query();
        if ($scope === 'filtered') {
            $this->applyEventFilters($query, $filters);
        }

        $matched = (clone $query)->count();
        $sample = (clone $query)
            ->orderByDesc('id')
            ->limit(5)
            ->get(['id', 'title', 'type', 'source_name'])
            ->map(static fn (Event $event): array => [
                'id' => (int) $event->id,
                'title' => (string) $event->title,
                'type' => (string) $event->type,
                'source_name' => is_string($event->source_name) ? $event->source_name : null,
            ])
            ->values();

        if ($dryRun) {
            return response()->json([
                'status' => 'dry_run',
                'scope' => $scope,
                'matched' => $matched,
                'limit' => $limit,
                'filters' => $filters,
                'sample' => $sample,
            ]);
        }

        $confirmToken = trim((string) ($validated['confirm_token'] ?? ''));
        if ($confirmToken !== self::BULK_DELETE_CONFIRM_TOKEN) {
            return response()->json([
                'message' => 'Invalid confirm token.',
                'expected_confirm_token' => self::BULK_DELETE_CONFIRM_TOKEN,
            ], 422);
        }

        $deleted = 0;
        if ($matched > 0) {
            if ($limit !== null) {
                $ids = (clone $query)
                    ->orderBy('id')
                    ->limit($limit)
                    ->pluck('id')
                    ->map(static fn (mixed $id): int => (int) $id)
                    ->all();

                if ($ids !== []) {
                    $deleted = Event::query()->whereIn('id', $ids)->delete();
                }
            } else {
                $deleted = (clone $query)->delete();
            }
        }

        Log::warning('Admin bulk event delete executed.', [
            'admin_id' => $request->user()?->id,
            'scope' => $scope,
            'matched' => $matched,
            'deleted' => $deleted,
            'limit' => $limit,
            'filters' => $filters,
        ]);

        return response()->json([
            'status' => 'ok',
            'scope' => $scope,
            'matched' => $matched,
            'deleted' => (int) $deleted,
            'limit' => $limit,
            'confirm_token' => self::BULK_DELETE_CONFIRM_TOKEN,
            'filters' => $filters,
        ]);
    }

    private function validateEvent(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'short' => ['nullable', 'string', 'max:180'],
            'type' => ['required', 'string', Rule::in(EventType::values())],
            'icon_emoji' => ['nullable', 'string', 'max:32'],
            'region_scope' => ['nullable', 'string', Rule::in(RegionScope::values())],
            'start_at' => ['required', 'date'],
            'end_at' => ['nullable', 'date'],
            'visibility' => ['required', Rule::in([0, 1])],
        ]);
    }

    private function normalizeIconEmoji(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function applyEventFilters(Builder $eventsQuery, array $filters): void
    {
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $term = '%'.$search.'%';
            $eventsQuery->where(function (Builder $query) use ($term): void {
                $query
                    ->where('title', 'like', $term)
                    ->orWhere('description', 'like', $term)
                    ->orWhere('short', 'like', $term);
            });
        }

        $type = trim((string) ($filters['type'] ?? ''));
        if ($type !== '' && in_array($type, EventType::values(), true)) {
            $eventsQuery->where('type', $type);
        }

        $visibility = $this->optionalIntValue($filters['visibility'] ?? null, 0, 1);
        if ($visibility !== null) {
            $eventsQuery->where('visibility', $visibility);
        }

        $sourceKind = strtolower(trim((string) ($filters['source_kind'] ?? '')));
        if ($sourceKind === 'manual') {
            $eventsQuery->where('source_name', 'manual');
        } elseif ($sourceKind === 'crawled') {
            $eventsQuery
                ->whereNotNull('source_name')
                ->where('source_name', '!=', 'manual');
        }

        $sourceName = trim((string) ($filters['source_name'] ?? ''));
        if ($sourceName !== '') {
            $eventsQuery->where('source_name', $sourceName);
        }

        $dateRange = $this->resolveDateRangeFromFilters($filters);
        if ($dateRange !== null) {
            [$rangeStartUtc, $rangeEndUtc] = $dateRange;
            $this->applyDateRangeFilter($eventsQuery, $rangeStartUtc, $rangeEndUtc);
        }
    }

    private function normalizeFilterPayload(array $filters): array
    {
        $visibility = $this->optionalIntValue($filters['visibility'] ?? null, 0, 1);
        $sourceKind = strtolower(trim((string) ($filters['source_kind'] ?? '')));
        if (! in_array($sourceKind, ['manual', 'crawled'], true)) {
            $sourceKind = '';
        }

        $type = trim((string) ($filters['type'] ?? ''));
        if (! in_array($type, EventType::values(), true)) {
            $type = '';
        }

        return [
            'search' => trim((string) ($filters['search'] ?? '')),
            'type' => $type,
            'visibility' => $visibility,
            'year' => $this->optionalIntValue($filters['year'] ?? null, 1900, 2200),
            'month' => $this->optionalIntValue($filters['month'] ?? null, 1, 12),
            'day' => $this->optionalIntValue($filters['day'] ?? null, 1, 31),
            'source_kind' => $sourceKind,
            'source_name' => trim((string) ($filters['source_name'] ?? '')),
        ];
    }

    private function hasAnyFilterValue(array $filters): bool
    {
        return trim((string) ($filters['search'] ?? '')) !== ''
            || trim((string) ($filters['type'] ?? '')) !== ''
            || ($filters['visibility'] ?? null) !== null
            || ($filters['year'] ?? null) !== null
            || ($filters['month'] ?? null) !== null
            || ($filters['day'] ?? null) !== null
            || trim((string) ($filters['source_kind'] ?? '')) !== ''
            || trim((string) ($filters['source_name'] ?? '')) !== '';
    }

    /**
     * @return array{0:CarbonImmutable,1:CarbonImmutable}|null
     */
    private function resolveDateRangeFromFilters(array $filters): ?array
    {
        $year = $this->optionalIntValue($filters['year'] ?? null, 1900, 2200);
        if ($year === null) {
            return null;
        }

        $month = $this->optionalIntValue($filters['month'] ?? null, 1, 12);
        $day = $this->optionalIntValue($filters['day'] ?? null, 1, 31);

        if ($month === null) {
            $start = CarbonImmutable::create($year, 1, 1, 0, 0, 0, 'UTC');
            return [$start, $start->endOfYear()];
        }

        $startOfMonth = CarbonImmutable::create($year, $month, 1, 0, 0, 0, 'UTC');
        if ($day === null || ! checkdate($month, $day, $year)) {
            return [$startOfMonth, $startOfMonth->endOfMonth()];
        }

        $startOfDay = CarbonImmutable::create($year, $month, $day, 0, 0, 0, 'UTC');

        return [$startOfDay, $startOfDay->endOfDay()];
    }

    private function applyDateRangeFilter(Builder $query, CarbonImmutable $fromUtc, CarbonImmutable $toUtc): void
    {
        $from = $fromUtc->format('Y-m-d H:i:s');
        $to = $toUtc->format('Y-m-d H:i:s');

        $query->where(function (Builder $rangeQuery) use ($from, $to): void {
            $rangeQuery
                ->whereBetween('start_at', [$from, $to])
                ->orWhere(function (Builder $fallbackQuery) use ($from, $to): void {
                    $fallbackQuery
                        ->whereNull('start_at')
                        ->whereBetween('max_at', [$from, $to]);
                });
        });
    }

    private function optionalIntValue(mixed $raw, int $min, int $max): ?int
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        $value = filter_var($raw, FILTER_VALIDATE_INT, [
            'options' => [
                'min_range' => $min,
                'max_range' => $max,
            ],
        ]);

        return $value === false ? null : (int) $value;
    }
}
