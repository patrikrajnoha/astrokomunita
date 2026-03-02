<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\EventViewingForecastRequest;
use App\Services\Events\EventViewingForecastService;
use App\Services\Events\PublishedEventQuery;
use App\Support\ApiResponse;
use App\Support\Sky\SkyContextResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class EventViewingForecastController extends Controller
{
    public function __construct(
        private readonly PublishedEventQuery $publishedEventQuery,
        private readonly SkyContextResolver $contextResolver,
        private readonly EventViewingForecastService $eventViewingForecastService
    ) {
    }

    public function show(EventViewingForecastRequest $request, int $id): JsonResponse
    {
        $event = $this->publishedEventQuery->base()->findOrFail($id);
        $context = $this->contextResolver->resolve($request, $request->validated());

        if (($context['coordinate_source'] ?? null) === 'fallback_config') {
            return ApiResponse::error('Viewing forecast requires a saved or explicit location.', [
                'code' => 'missing_location',
            ], 400);
        }

        $cacheKey = implode(':', [
            'event_viewing_forecast',
            $event->id,
            $event->updated_at?->timestamp ?? 0,
            number_format($context['lat'], 6, '.', ''),
            number_format($context['lon'], 6, '.', ''),
            str_replace(':', '_', $context['tz']),
        ]);
        $ttlMinutes = max(15, (int) config('observing.sky.viewing_forecast_cache_ttl_minutes', 30));

        try {
            $payload = Cache::remember(
                $cacheKey,
                now()->addMinutes($ttlMinutes),
                fn (): array => $this->eventViewingForecastService->build(
                    $event,
                    $context['lat'],
                    $context['lon'],
                    $context['tz']
                )
            );
        } catch (\Throwable) {
            return ApiResponse::error('Viewing forecast is temporarily unavailable.', null, 503);
        }

        return response()->json($payload);
    }
}
