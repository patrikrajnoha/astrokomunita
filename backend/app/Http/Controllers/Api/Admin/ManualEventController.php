<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\EventType;
use App\Http\Controllers\Controller;
use App\Http\Resources\EventResource;
use App\Models\Event;
use App\Models\ManualEvent;
use App\Support\EventTime;
use App\Services\Events\EventFeedRealtimePublisher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ManualEventController extends Controller
{
    public function __construct(
        private readonly EventFeedRealtimePublisher $eventFeedRealtimePublisher,
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

        $query = ManualEvent::query()->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('type')) {
            $query->where('event_type', $request->query('type'));
        }

        if ($request->filled('q')) {
            $q = $request->query('q');
            $query->where('title', 'like', "%{$q}%");
        }

        return response()->json($query->paginate($perPage));
    }

    public function store(Request $request)
    {
        $validated = $this->validateManual($request);

        $manual = ManualEvent::create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'event_type' => $validated['event_type'],
            'starts_at' => $validated['starts_at'],
            'ends_at' => $validated['ends_at'] ?? null,
            'time_type' => EventTime::TYPE_START,
            'time_precision' => EventTime::PRECISION_EXACT,
            'visibility' => $validated['visibility'] ?? 1,
            'created_by' => $request->user()->id,
            'status' => 'draft',
        ]);

        return response()->json($manual, 201);
    }

    public function update(Request $request, ManualEvent $manualEvent)
    {
        $validated = $this->validateManual($request);

        $manualEvent->fill([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'event_type' => $validated['event_type'],
            'starts_at' => $validated['starts_at'],
            'ends_at' => $validated['ends_at'] ?? null,
            'time_type' => EventTime::TYPE_START,
            'time_precision' => EventTime::PRECISION_EXACT,
            'visibility' => $validated['visibility'] ?? $manualEvent->visibility,
        ]);
        $manualEvent->save();

        return response()->json($manualEvent);
    }

    public function destroy(ManualEvent $manualEvent)
    {
        $manualEvent->delete();
        return response()->noContent();
    }

    public function publish(ManualEvent $manualEvent)
    {
        if ($manualEvent->status === 'published' && $manualEvent->published_event_id) {
            return response()->json([
                'message' => 'Already published.',
                'event_id' => $manualEvent->published_event_id,
            ], 409);
        }

        $event = new Event();
        $event->title = $manualEvent->title;
        $event->description = $manualEvent->description;
        $event->type = $manualEvent->event_type;
        $event->start_at = $manualEvent->starts_at;
        $event->end_at = $manualEvent->ends_at;
        $event->max_at = $manualEvent->starts_at;
        $event->time_type = $manualEvent->time_type ?: EventTime::TYPE_START;
        $event->time_precision = $manualEvent->time_precision ?: EventTime::PRECISION_EXACT;
        $event->visibility = $manualEvent->visibility ?? 1;
        $event->source_name = 'manual';
        $event->source_uid = (string) Str::uuid();
        $event->save();
        $this->eventFeedRealtimePublisher->publish($event);

        $manualEvent->status = 'published';
        $manualEvent->published_event_id = $event->id;
        $manualEvent->save();

        return new EventResource($event);
    }

    public function publishBatch(Request $request)
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        $validated = $request->validate([
            'status' => ['nullable', 'string', 'max:50'],
            'type' => ['nullable', 'string', 'in:' . implode(',', EventType::values())],
            'q' => ['nullable', 'string', 'max:200'],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:5000'],
        ]);

        $status = $validated['status'] ?? 'draft';
        $type = $validated['type'] ?? null;
        $q = isset($validated['q']) ? trim((string) $validated['q']) : null;
        $year = $validated['year'] ?? null;
        $month = $validated['month'] ?? null;
        $limit = (int) ($validated['limit'] ?? 1000);

        $query = ManualEvent::query()
            ->when($status, fn ($qq) => $qq->where('status', $status))
            ->when($type, fn ($qq) => $qq->where('event_type', $type))
            ->when($year && $month, function ($qq) use ($year, $month) {
                $start = sprintf('%04d-%02d-01 00:00:00', (int) $year, (int) $month);
                $end = date('Y-m-t 23:59:59', strtotime($start));
                $qq->whereBetween('starts_at', [$start, $end]);
            })
            ->when($q !== null && $q !== '', function ($qq) use ($q) {
                $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
                $qq->where(function ($sub) use ($like) {
                    $sub->where('title', 'like', $like)
                        ->orWhere('description', 'like', $like);
                });
            });

        $ids = $query->orderByDesc('id')->limit($limit)->pluck('id')->map(fn ($id) => (int) $id)->all();
        if ($ids === []) {
            return response()->json([
                'ok' => true,
                'published' => 0,
                'failed' => 0,
                'total_selected' => 0,
                'limit_applied' => $limit,
            ]);
        }

        $published = 0;
        $failed = 0;

        foreach ($ids as $manualEventId) {
            try {
                $manual = ManualEvent::query()->find($manualEventId);
                if (! $manual || $manual->status === 'published') {
                    continue;
                }

                $event = new Event();
                $event->title = $manual->title;
                $event->description = $manual->description;
                $event->type = $manual->event_type;
                $event->start_at = $manual->starts_at;
                $event->end_at = $manual->ends_at;
                $event->max_at = $manual->starts_at;
                $event->time_type = $manual->time_type ?: EventTime::TYPE_START;
                $event->time_precision = $manual->time_precision ?: EventTime::PRECISION_EXACT;
                $event->visibility = $manual->visibility ?? 1;
                $event->source_name = 'manual';
                $event->source_uid = (string) Str::uuid();
                $event->save();
                $this->eventFeedRealtimePublisher->publish($event);

                $manual->status = 'published';
                $manual->published_event_id = $event->id;
                $manual->save();

                $published++;
            } catch (\Throwable $exception) {
                $failed++;
                Log::warning('Manual event batch publish failed', [
                    'manual_event_id' => $manualEventId,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return response()->json([
            'ok' => true,
            'published' => $published,
            'failed' => $failed,
            'total_selected' => count($ids),
            'limit_applied' => $limit,
        ]);
    }

    private function validateManual(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'event_type' => ['required', 'string', 'in:' . implode(',', EventType::values())],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date'],
            'visibility' => ['nullable', 'integer'],
        ]);
    }
}
