<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminStatsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminStatsController extends Controller
{
    public function __construct(
        private readonly AdminStatsService $statsService,
    ) {
    }

    public function index(): \Illuminate\Http\JsonResponse
    {
        return response()->json($this->resolveStatsPayload());
    }

    public function export(Request $request): StreamedResponse|\Illuminate\Http\JsonResponse
    {
        $format = strtolower((string) $request->query('format', 'csv'));
        if (!in_array($format, ['csv', 'json'], true)) {
            $format = 'csv';
        }

        $payload = $this->resolveStatsPayload();

        if ($format === 'json') {
            return response()->json($payload);
        }

        $filename = 'admin_stats_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($payload): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            fputcsv($handle, ['section', 'metric', 'value']);
            foreach ($payload['kpi'] as $metric => $value) {
                fputcsv($handle, ['kpi', $metric, (int) $value]);
            }
            foreach ($payload['demographics']['by_role'] as $metric => $value) {
                fputcsv($handle, ['demographics.by_role', (string) $metric, (int) $value]);
            }
            foreach ($payload['demographics']['by_region'] as $metric => $value) {
                fputcsv($handle, ['demographics.by_region', (string) $metric, (int) $value]);
            }
            foreach (($payload['queues'] ?? []) as $metric => $value) {
                fputcsv($handle, ['queues', (string) $metric, (int) $value]);
            }

            fputcsv($handle, ['trend', 'range_days', (int) $payload['trend']['range_days']]);
            fputcsv($handle, []);
            fputcsv($handle, ['date', 'new_users', 'new_posts', 'new_events']);

            foreach ($payload['trend']['points'] as $point) {
                fputcsv($handle, [
                    (string) ($point['date'] ?? ''),
                    (int) ($point['new_users'] ?? 0),
                    (int) ($point['new_posts'] ?? 0),
                    (int) ($point['new_events'] ?? 0),
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * last_login_at fallback:
     * if users.last_login_at is unavailable, active_30d uses users.created_at.
     *
     * @return array<string,mixed>
     */
    private function resolveStatsPayload(): array
    {
        $ttl = (int) config('admin.stats_cache_ttl_seconds', 60);
        $ttl = max(1, $ttl);
        $cacheKey = 'admin:stats:v1';

        return Cache::remember($cacheKey, now()->addSeconds($ttl), fn () => $this->statsService->build(30));
    }
}
