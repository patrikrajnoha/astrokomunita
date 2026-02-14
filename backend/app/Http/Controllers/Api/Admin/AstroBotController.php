<?php

namespace App\Http\Controllers\Api\Admin;

use App\Exceptions\AstroBotSyncInProgressException;
use App\Http\Controllers\Controller;
use App\Jobs\TranslateRssItemJob;
use App\Models\Post;
use App\Models\RssItem;
use App\Services\AstroBotNasaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AstroBotController extends Controller
{
    public function __construct(
        private readonly AstroBotNasaService $nasaService,
    ) {
    }

    public function nasaStatus(): JsonResponse
    {
        $run = $this->nasaService->latestRun();

        return response()->json([
            'mode' => 'automatic',
            'enabled' => (bool) config('astrobot.enabled', true),
            'source' => AstroBotNasaService::SOURCE,
            'keep_max_items' => (int) config('astrobot.keep_max_items', 30),
            'keep_max_days' => (int) config('astrobot.keep_max_days', 14),
            'last_run' => $run ? [
                'id' => $run->id,
                'status' => $run->status,
                'trigger' => $run->trigger,
                'started_at' => optional($run->started_at)->toIso8601String(),
                'finished_at' => optional($run->finished_at)->toIso8601String(),
                'duration_ms' => $run->duration_ms,
                'new_items' => $run->new_items,
                'published_items' => $run->published_items,
                'deleted_items' => $run->deleted_items,
                'errors' => $run->errors,
                'error_message' => $run->error_message,
            ] : null,
        ]);
    }

    public function syncNow(): JsonResponse
    {
        try {
            $result = $this->nasaService->syncWithLock('manual');
        } catch (AstroBotSyncInProgressException) {
            return response()->json([
                'message' => 'NASA RSS sync is already running.',
            ], 409);
        }

        return response()->json([
            'message' => 'NASA RSS sync completed.',
            'result' => $result,
        ]);
    }

    // Backward-compatible alias.
    public function syncRss(): JsonResponse
    {
        return $this->syncNow();
    }

    public function refreshRss(Request $request): JsonResponse
    {
        return $this->syncNow();
    }

    public function items(Request $request): JsonResponse
    {
        $request->validate([
            'scope' => 'in:today,all',
            'status' => 'string',
            'source' => 'string',
            'search' => 'string|max:255',
            'per_page' => 'integer|min:1|max:200',
            'page' => 'integer|min:1',
        ]);

        $query = RssItem::query();

        $scope = (string) $request->get('scope', 'all');
        if ($scope === 'today') {
            $query->today();
        }

        if ($status = $request->get('status')) {
            $statuses = array_map('trim', explode(',', (string) $status));
            $query->byStatus($statuses);
        }

        if ($source = $request->get('source')) {
            $query->bySource((string) $source);
        }

        if ($search = trim((string) $request->get('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', '%' . $search . '%')
                    ->orWhere('summary', 'like', '%' . $search . '%');
            });
        }

        $query->orderByDesc('published_at')->orderByDesc('id');

        $perPage = (int) $request->get('per_page', 50);
        return response()->json($query->paginate($perPage));
    }

    public function posts(Request $request): JsonResponse
    {
        $request->validate([
            'scope' => 'in:today,all',
            'per_page' => 'integer|min:1|max:200',
            'page' => 'integer|min:1',
        ]);

        $query = Post::query()
            ->whereIn('source_name', ['astrobot', 'nasa_rss'])
            ->with('user:id,name,email')
            ->orderByDesc('created_at');

        if ((string) $request->get('scope', 'today') === 'today') {
            $query->whereDate('created_at', now()->toDateString());
        }

        $perPage = (int) $request->get('per_page', 50);
        return response()->json($query->paginate($perPage));
    }

    public function update(RssItem $item, Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Manual item editing is disabled in automatic mode.',
        ], 410);
    }

    public function publish(RssItem $item, Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Manual publish is disabled. NASA RSS is fully automatic.',
        ], 410);
    }

    public function reject(RssItem $item, Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Manual review is disabled. NASA RSS is fully automatic.',
        ], 410);
    }

    public function discard(RssItem $item, Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Manual discard is disabled. NASA RSS is fully automatic.',
        ], 410);
    }

    public function deletePost(Post $post): JsonResponse
    {
        return response()->json([
            'message' => 'Manual deletion is disabled. Retention is automatic.',
        ], 410);
    }

    public function retranslate(RssItem $item, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'force' => 'boolean',
        ]);

        $force = (bool) ($validated['force'] ?? true);

        TranslateRssItemJob::dispatch($item->id, $force)->afterCommit();

        Log::info('AstroBot admin requested rss item retranslation', [
            'admin_user_id' => $request->user()?->id,
            'rss_item_id' => $item->id,
            'force' => $force,
        ]);

        return response()->json([
            'message' => 'Retranslation queued.',
            'item_id' => $item->id,
            'force' => $force,
        ]);
    }

    public function retranslatePending(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'limit' => 'nullable|integer|min:1|max:100',
            'force' => 'boolean',
        ]);

        $limit = (int) ($validated['limit'] ?? 100);
        $force = (bool) ($validated['force'] ?? false);

        $itemIds = RssItem::query()
            ->whereIn('translation_status', [
                RssItem::TRANSLATION_PENDING,
                RssItem::TRANSLATION_FAILED,
            ])
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->pluck('id');

        foreach ($itemIds as $itemId) {
            TranslateRssItemJob::dispatch((int) $itemId, $force)->afterCommit();
        }

        Log::info('AstroBot admin requested pending retranslation batch', [
            'admin_user_id' => $request->user()?->id,
            'items_queued' => $itemIds->count(),
            'force' => $force,
            'limit' => $limit,
        ]);

        return response()->json([
            'message' => 'Pending retranslation batch queued.',
            'queued' => $itemIds->count(),
        ]);
    }
}
