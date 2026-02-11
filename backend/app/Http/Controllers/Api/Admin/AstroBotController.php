<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\RssItem;
use App\Services\AstroBotPublisher;
use App\Services\AstroBotSyncOrchestratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AstroBotController extends Controller
{
    public function __construct(
        private readonly AstroBotPublisher $publisher,
        private readonly AstroBotSyncOrchestratorService $syncService,
    ) {
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

        $query = RssItem::query()->with('reviewer:id,name,email');

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

        $query->orderByRaw("
            CASE status
                WHEN 'needs_review' THEN 1
                WHEN 'draft' THEN 2
                WHEN 'published' THEN 3
                WHEN 'rejected' THEN 4
                ELSE 5
            END ASC,
            published_at DESC,
            fetched_at DESC,
            id DESC
        ");

        $perPage = (int) $request->get('per_page', 50);
        return response()->json($query->paginate($perPage));
    }

    public function publish(RssItem $item, Request $request): JsonResponse
    {
        $request->validate([
            'content' => 'nullable|string|max:5000',
            'summary' => 'nullable|string|max:2000',
        ]);

        try {
            $post = $this->publisher->publish($item, $request->only(['content', 'summary']));
            $item->update([
                'reviewed_by' => $request->user()?->id,
                'reviewed_at' => now(),
                'review_note' => null,
            ]);

            return response()->json([
                'message' => 'Item published.',
                'post_id' => $post->id,
                'status' => $item->fresh()->status,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function reject(RssItem $item, Request $request): JsonResponse
    {
        $request->validate([
            'note' => 'nullable|string|max:2000',
        ]);

        $this->publisher->reject($item, $request->string('note')->toString(), $request->user()?->id);

        return response()->json([
            'message' => 'Item rejected.',
            'status' => $item->fresh()->status,
        ]);
    }

    // Backward-compatible alias for old endpoint naming.
    public function discard(RssItem $item, Request $request): JsonResponse
    {
        return $this->reject($item, $request);
    }

    public function update(RssItem $item, Request $request): JsonResponse
    {
        $request->validate([
            'title' => 'required|string|max:500',
            'summary' => 'nullable|string|max:2000',
        ]);

        $item->update([
            'title' => (string) $request->get('title'),
            'summary' => $request->get('summary'),
            'status' => $item->status === RssItem::STATUS_PUBLISHED
                ? RssItem::STATUS_PUBLISHED
                : RssItem::STATUS_NEEDS_REVIEW,
        ]);

        return response()->json(['message' => 'Item updated.']);
    }

    public function posts(Request $request): JsonResponse
    {
        $request->validate([
            'scope' => 'in:today,all',
            'per_page' => 'integer|min:1|max:200',
            'page' => 'integer|min:1',
        ]);

        $query = Post::query()
            ->where('source_name', 'astrobot')
            ->with('user:id,name,email')
            ->orderByDesc('created_at');

        if ((string) $request->get('scope', 'today') === 'today') {
            $query->whereDate('created_at', now()->toDateString());
        }

        $perPage = (int) $request->get('per_page', 50);
        return response()->json($query->paginate($perPage));
    }

    public function deletePost(Post $post): JsonResponse
    {
        if ($post->source_name !== 'astrobot') {
            return response()->json(['message' => 'Only AstroBot posts can be deleted via this endpoint.'], 403);
        }

        $post->delete();

        return response()->json(['message' => 'Post hidden.']);
    }

    public function syncRss(): JsonResponse
    {
        $result = $this->syncService->syncAndProcess();

        return response()->json([
            'message' => 'RSS sync completed.',
            'result' => $result,
            'server_time' => now()->toIso8601String(),
            'last_synced_at' => $this->syncService->lastSyncAt(),
        ]);
    }

    public function refreshRss(Request $request): JsonResponse
    {
        return $this->syncRss();
    }
}

