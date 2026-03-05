<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Sky\SkyAstronomyService;
use App\Services\Sky\SkyWeatherService;
use App\Support\ApiResponse;
use App\Support\Sky\SkyContextResolver;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SkyController extends Controller
{
    public function __construct(
        private readonly SkyContextResolver $contextResolver,
        private readonly SkyWeatherService $skyWeatherService,
        private readonly SkyAstronomyService $skyAstronomyService,
    ) {
    }

    public function weather(Request $request): JsonResponse
    {
        $context = $this->contextResolver->resolve($request, [
            'lat' => $request->query('lat'),
            'lon' => $request->query('lon'),
            'tz' => $request->query('tz'),
        ]);

        $cacheKey = $this->buildCacheKey(
            'sky_weather',
            $context['lat'],
            $context['lon'],
            $context['tz'],
            'open_meteo'
        );
        $ttlMinutes = max(1, (int) config('observing.sky.weather_cache_ttl_minutes', 10));

        try {
            $payload = Cache::remember(
                $cacheKey,
                now()->addMinutes($ttlMinutes),
                fn (): array => $this->skyWeatherService->fetch($context['lat'], $context['lon'], $context['tz'])
            );
        } catch (\Throwable) {
            return ApiResponse::error('Sky weather is temporarily unavailable.', null, 503);
        }

        return response()->json($payload);
    }

    public function astronomy(Request $request): JsonResponse
    {
        $context = $this->contextResolver->resolve($request, [
            'lat' => $request->query('lat'),
            'lon' => $request->query('lon'),
            'tz' => $request->query('tz'),
        ]);

        $dateKey = CarbonImmutable::now($context['tz'])->format('Y-m-d');
        $cacheKey = $this->buildCacheKey('sky_astronomy', $context['lat'], $context['lon'], $context['tz'], $dateKey);
        $ttlHours = max(1, (int) config('observing.sky.astronomy_cache_ttl_hours', 6));

        try {
            $payload = Cache::remember(
                $cacheKey,
                now()->addHours($ttlHours),
                fn (): array => $this->skyAstronomyService->fetch($context['lat'], $context['lon'], $context['tz'])
            );
        } catch (\Throwable) {
            return ApiResponse::error('Sky astronomy data is temporarily unavailable.', null, 503);
        }

        return response()->json($payload);
    }

    private function buildCacheKey(string $prefix, float $lat, float $lon, string $tz, ?string $suffix = null): string
    {
        $parts = [
            $prefix,
            number_format($lat, 6, '.', ''),
            number_format($lon, 6, '.', ''),
            str_replace(':', '_', $tz),
        ];

        if ($suffix !== null && $suffix !== '') {
            $parts[] = $suffix;
        }

        return implode(':', $parts);
    }
}
