<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Widgets\SidebarWidgetBundleService;
use App\Support\Sky\SkyContextResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SidebarDataController extends Controller
{
    public function __construct(
        private readonly SidebarWidgetBundleService $sidebarWidgetBundleService,
        private readonly SkyContextResolver $skyContextResolver,
    ) {
    }

    public function show(Request $request): JsonResponse
    {
        $sections = $this->extractRequestedSections($request);
        $normalizedSections = $this->sidebarWidgetBundleService->normalizeRequestedSections($sections);
        $skyContext = $this->extractSkyContext($request, $normalizedSections);

        return response()->json([
            'requested_sections' => $normalizedSections,
            'data' => $this->sidebarWidgetBundleService->payloadForSections($normalizedSections, $skyContext),
        ]);
    }

    /**
     * @return list<string>
     */
    private function extractRequestedSections(Request $request): array
    {
        $raw = $request->query('sections');

        if (is_string($raw)) {
            return array_values(array_filter(array_map(
                static fn (string $entry): string => trim($entry),
                explode(',', $raw),
            ), static fn (string $entry): bool => $entry !== ''));
        }

        if (! is_array($raw)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (mixed $entry): string => trim((string) $entry),
            $raw,
        ), static fn (string $entry): bool => $entry !== ''));
    }

    /**
     * @param  list<string>  $sections
     * @return array{lat:float,lon:float,tz:string}|null
     */
    private function extractSkyContext(Request $request, array $sections): ?array
    {
        $needsObservingContext = in_array('space_weather', $sections, true)
            || in_array('aurora_watch', $sections, true);
        $needsObservingContext = $needsObservingContext
            || in_array('observing_conditions', $sections, true)
            || in_array('observing_weather', $sections, true)
            || in_array('night_sky', $sections, true)
            || in_array('iss_pass', $sections, true);

        if (! $needsObservingContext) {
            return null;
        }

        $validated = [];
        $lat = $this->toValidFloat($request->query('lat'), -90, 90);
        $lon = $this->toValidFloat($request->query('lon'), -180, 180);
        $tz = $this->toValidTimezone($request->query('tz'));

        if ($lat !== null) {
            $validated['lat'] = $lat;
        }
        if ($lon !== null) {
            $validated['lon'] = $lon;
        }
        if ($tz !== null) {
            $validated['tz'] = $tz;
        }

        if (! array_key_exists('lat', $validated) || ! array_key_exists('lon', $validated)) {
            return null;
        }

        $context = $this->skyContextResolver->resolve($request, $validated);

        return [
            'lat' => $context['lat'],
            'lon' => $context['lon'],
            'tz' => $context['tz'],
        ];
    }

    private function toValidFloat(mixed $value, float $min, float $max): ?float
    {
        if (! is_numeric($value)) {
            return null;
        }

        $parsed = (float) $value;
        if ($parsed < $min || $parsed > $max) {
            return null;
        }

        return $parsed;
    }

    private function toValidTimezone(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        return in_array($trimmed, timezone_identifiers_list(), true) ? $trimmed : null;
    }
}
