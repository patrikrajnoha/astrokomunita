<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Location\OpenMeteoGeocodingService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MetaController extends Controller
{
    public function __construct(
        private readonly OpenMeteoGeocodingService $geocodingService
    ) {
    }

    public function interests()
    {
        return response()->json([
            'data' => config('onboarding.interests', []),
        ]);
    }

    public function locations(Request $request)
    {
        $query = trim((string) $request->query('q', ''));
        $limit = max(1, min((int) $request->query('limit', 8), 8));

        if ($query === '') {
            return response()->json(['data' => []]);
        }

        $rows = collect($this->geocodingService->search($query, $limit))
            ->take($limit)
            ->values();

        if ($rows->isEmpty()) {
            $rows = $this->fallbackLocations($query, $limit);
        }

        return response()->json([
            'data' => $rows,
        ]);
    }

    /**
     * @return \Illuminate\Support\Collection<int,array{
     *   label:string,
     *   place_id:string,
     *   lat:float,
     *   lon:float,
     *   timezone:?string,
     *   country:?string
     * }>
     */
    private function fallbackLocations(string $query, int $limit)
    {
        $needle = $this->normalizeLookup($query);

        return collect(config('onboarding.locations', []))
            ->filter(function ($item) {
                return is_array($item)
                    && is_string($item['label'] ?? null)
                    && is_string($item['place_id'] ?? null)
                    && isset($item['lat'], $item['lon']);
            })
            ->map(function (array $item) use ($needle) {
                $haystack = $this->normalizeLookup($item['label']);

                $score = null;
                if (str_starts_with($haystack, $needle)) {
                    $score = 0;
                } elseif (str_contains($haystack, ' ' . $needle)) {
                    $score = 1;
                } elseif (str_contains($haystack, $needle)) {
                    $score = 2;
                }

                if ($score === null) {
                    return null;
                }

                return [
                    'score' => $score,
                    'label' => $item['label'],
                    'place_id' => $item['place_id'],
                    'lat' => (float) $item['lat'],
                    'lon' => (float) $item['lon'],
                    'timezone' => null,
                    'country' => null,
                ];
            })
            ->filter()
            ->sortBy([
                ['score', 'asc'],
                ['label', 'asc'],
            ])
            ->values()
            ->take($limit)
            ->map(fn (array $item) => [
                'label' => $item['label'],
                'place_id' => $item['place_id'],
                'lat' => $item['lat'],
                'lon' => $item['lon'],
                'timezone' => $item['timezone'],
                'country' => $item['country'],
            ])
            ->values();
    }

    private function normalizeLookup(string $value): string
    {
        $ascii = Str::of($value)->ascii()->lower()->value();
        $clean = preg_replace('/[^a-z0-9]+/i', ' ', $ascii);

        return is_string($clean) ? trim((string) preg_replace('/\s+/', ' ', $clean)) : '';
    }
}
