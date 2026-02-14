<?php

namespace App\Services;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;

class EventService
{
    /**
     * Get paginated events with optional filters
     */
    public function getEventsPaginated(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = Event::query()
            ->with(['user'])
            ->orderBy('start_at', 'desc');

        // Apply filters
        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['visibility'])) {
            $query->where('visibility', $filters['visibility']);
        }

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if (isset($filters['date_from'])) {
            $query->whereDate('start_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->whereDate('start_at', '<=', $filters['date_to']);
        }

        return $query->paginate($perPage);
    }

    /**
     * Create a new event
     */
    public function createEvent(array $data): Event
    {
        return DB::transaction(function () use ($data) {
            $event = Event::create([
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'type' => $data['type'],
                'start_at' => $data['start_at'],
                'end_at' => $data['end_at'] ?? null,
                'visibility' => $data['visibility'] ?? 1,
                'max_at' => $data['max_at'] ?? $data['start_at'],
            ]);

            return $event->load(['user']);
        });
    }

    /**
     * Update an existing event
     */
    public function updateEvent(Event $event, array $data): Event
    {
        return DB::transaction(function () use ($event, $data) {
            $updateData = [
                'title' => $data['title'] ?? $event->title,
                'description' => $data['description'] ?? $event->description,
                'type' => $data['type'] ?? $event->type,
                'visibility' => $data['visibility'] ?? $event->visibility,
            ];

            if (isset($data['start_at'])) {
                $updateData['start_at'] = $data['start_at'];
                $updateData['max_at'] = $data['start_at'];
            }

            if (isset($data['end_at'])) {
                $updateData['end_at'] = $data['end_at'];
            }

            $event->update($updateData);

            return $event->load(['user']);
        });
    }

    /**
     * Delete an event
     */
    public function deleteEvent(Event $event): bool
    {
        return DB::transaction(function () use ($event) {
            return $event->delete();
        });
    }

    /**
     * Get upcoming events
     */
    public function getUpcomingEvents(int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        return Event::query()
            ->where('start_at', '>', now())
            ->where('visibility', 1)
            ->orderBy('start_at', 'asc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get events by date range
     */
    public function getEventsByDateRange(\DateTime $startDate, \DateTime $endDate): \Illuminate\Database\Eloquent\Collection
    {
        return Event::query()
            ->whereBetween('start_at', [$startDate, $endDate])
            ->where('visibility', 1)
            ->orderBy('start_at', 'asc')
            ->get();
    }

    /**
     * Toggle event visibility
     */
    public function toggleVisibility(Event $event): Event
    {
        $event->visibility = $event->visibility === 1 ? 0 : 1;
        $event->save();

        return $event;
    }

    /**
     * Get event statistics
     */
    public function getEventStatistics(): array
    {
        return [
            'total' => Event::count(),
            'public' => Event::where('visibility', 1)->count(),
            'hidden' => Event::where('visibility', 0)->count(),
            'upcoming' => Event::where('start_at', '>', now())->count(),
            'past' => Event::where('start_at', '<=', now())->count(),
            'by_type' => Event::selectRaw('type, count(*) as count')
                ->groupBy('type')
                ->pluck('count', 'type')
                ->toArray(),
        ];
    }
}
