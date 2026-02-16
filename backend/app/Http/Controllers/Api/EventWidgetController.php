<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventCandidate;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class EventWidgetController extends Controller
{
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
                'generated_at' => now()->toIso8601String(),
            ];
        });

        return response()->json($payload);
    }

    private function basePublishedQuery()
    {
        return Event::query()
            ->where('visibility', 1)
            ->published()
            ->where(function ($sub) {
                $sub->where('source_name', 'manual')
                    ->orWhereExists(function ($q) {
                        $q->selectRaw('1')
                            ->from('event_candidates')
                            ->whereColumn('event_candidates.published_event_id', 'events.id')
                            ->where('event_candidates.status', EventCandidate::STATUS_APPROVED);
                    });
            });
    }
}
