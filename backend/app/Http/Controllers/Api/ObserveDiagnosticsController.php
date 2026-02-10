<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Observing\ObservingSummaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ObserveDiagnosticsController extends Controller
{
    public function __construct(
        private readonly ObservingSummaryService $summaryService
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        if (!app()->environment('local')) {
            throw new NotFoundHttpException();
        }

        $validated = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lon' => ['required', 'numeric', 'between:-180,180'],
            'date' => ['required', 'date_format:Y-m-d'],
            'tz' => ['nullable', 'string'],
        ]);

        $lat = (float) $validated['lat'];
        $lon = (float) $validated['lon'];
        $date = (string) $validated['date'];
        $tz = $this->sanitizeTimezone((string) ($validated['tz'] ?? ''));

        return response()->json(
            $this->summaryService->diagnostics($lat, $lon, $date, $tz)
        );
    }

    private function sanitizeTimezone(string $raw): string
    {
        $default = (string) config('observing.default_timezone', 'Europe/Bratislava');
        $trimmed = trim($raw, " \t\n\r\0\x0B\"'");

        if ($trimmed === '') {
            return $default;
        }

        return in_array($trimmed, timezone_identifiers_list(), true) ? $trimmed : $default;
    }
}

