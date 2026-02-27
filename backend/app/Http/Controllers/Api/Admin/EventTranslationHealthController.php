<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\EventCandidate;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class EventTranslationHealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $windowStart = Carbon::now()->subDay();
        $queueConnection = strtolower(trim((string) config('queue.default', 'sync')));

        $counts24h = EventCandidate::query()
            ->where('updated_at', '>=', $windowStart)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN translation_status = 'done' THEN 1 ELSE 0 END) as done")
            ->selectRaw("SUM(CASE WHEN translation_status = 'failed' THEN 1 ELSE 0 END) as failed")
            ->selectRaw("SUM(CASE WHEN translation_status = 'pending' THEN 1 ELSE 0 END) as pending")
            ->first();

        $errorStats = EventCandidate::query()
            ->where('updated_at', '>=', $windowStart)
            ->whereNotNull('translation_error')
            ->where('translation_error', '<>', '')
            ->selectRaw('translation_error')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('translation_error')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(fn (EventCandidate $row): array => [
                'error_code' => (string) $row->translation_error,
                'count' => (int) $row->total,
            ])
            ->values();

        $lastDone = EventCandidate::query()
            ->where('translation_status', EventCandidate::TRANSLATION_DONE)
            ->whereNotNull('translated_at')
            ->latest('translated_at')
            ->first(['id', 'source_name', 'translated_at']);

        $lastFailed = EventCandidate::query()
            ->where('translation_status', EventCandidate::TRANSLATION_FAILED)
            ->latest('updated_at')
            ->first(['id', 'source_name', 'translation_error', 'updated_at']);

        $pendingCandidates = EventCandidate::query()
            ->where('translation_status', EventCandidate::TRANSLATION_PENDING)
            ->count();

        $queuedJobs = 0;
        if ($queueConnection === 'database') {
            $queuedJobs = (int) DB::table('jobs')
                ->where('payload', 'like', '%TranslateEventCandidateJob%')
                ->count();
        }

        $total24h = (int) ($counts24h->total ?? 0);
        $failed24h = (int) ($counts24h->failed ?? 0);

        return response()->json([
            'window' => [
                'from' => $windowStart->toIso8601String(),
                'to' => Carbon::now()->toIso8601String(),
            ],
            'translation' => [
                'events_enabled' => (bool) config('translation.events.enabled', true),
                'default_provider' => (string) config('translation.default_provider', 'libretranslate'),
                'fallback_provider' => (string) config('translation.fallback_provider', 'none'),
            ],
            'queue' => [
                'connection' => $queueConnection,
                'allow_sync_queue' => (bool) config('translation.allow_sync_queue', false),
                'prefer_sync_in_local' => (bool) config('translation.events.prefer_sync_in_local', true),
                'queued_event_translation_jobs' => $queuedJobs,
            ],
            'counts_24h' => [
                'total' => $total24h,
                'done' => (int) ($counts24h->done ?? 0),
                'failed' => $failed24h,
                'pending' => (int) ($counts24h->pending ?? 0),
                'error_rate_24h_percent' => $total24h > 0 ? round(($failed24h / $total24h) * 100, 2) : 0.0,
            ],
            'pending_candidates_total' => $pendingCandidates,
            'top_error_codes_24h' => $errorStats,
            'last_done' => $lastDone ? [
                'candidate_id' => (int) $lastDone->id,
                'source_name' => (string) $lastDone->source_name,
                'at' => $lastDone->translated_at?->toIso8601String(),
            ] : null,
            'last_failed' => $lastFailed ? [
                'candidate_id' => (int) $lastFailed->id,
                'source_name' => (string) $lastFailed->source_name,
                'error_code' => (string) ($lastFailed->translation_error ?? ''),
                'at' => $lastFailed->updated_at?->toIso8601String(),
            ] : null,
        ]);
    }
}

