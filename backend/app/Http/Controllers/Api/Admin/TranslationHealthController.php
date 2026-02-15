<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\TranslationLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class TranslationHealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $windowStart = Carbon::now()->subDay();

        $total = TranslationLog::query()
            ->where('created_at', '>=', $windowStart)
            ->count();

        $failed = TranslationLog::query()
            ->where('created_at', '>=', $windowStart)
            ->where('status', 'failed')
            ->count();

        $lastSuccess = TranslationLog::query()
            ->where('status', 'success')
            ->latest('created_at')
            ->first(['provider', 'created_at']);

        $providerStats = TranslationLog::query()
            ->where('created_at', '>=', $windowStart)
            ->selectRaw('provider')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success")
            ->selectRaw("SUM(CASE WHEN status = 'cached' THEN 1 ELSE 0 END) as cached")
            ->selectRaw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed")
            ->groupBy('provider')
            ->orderBy('provider')
            ->get()
            ->map(fn (TranslationLog $row): array => [
                'provider' => (string) $row->provider,
                'total' => (int) $row->total,
                'success' => (int) $row->success,
                'cached' => (int) $row->cached,
                'failed' => (int) $row->failed,
            ])
            ->values();

        return response()->json([
            'window' => [
                'from' => $windowStart->toIso8601String(),
                'to' => Carbon::now()->toIso8601String(),
            ],
            'last_successful_translation' => $lastSuccess ? [
                'provider' => (string) $lastSuccess->provider,
                'at' => $lastSuccess->created_at?->toIso8601String(),
            ] : null,
            'error_rate_24h_percent' => $total > 0 ? round(($failed / $total) * 100, 2) : 0.0,
            'counts_24h' => [
                'total' => $total,
                'failed' => $failed,
                'successful' => $total - $failed,
            ],
            'providers_24h' => $providerStats,
        ]);
    }
}
