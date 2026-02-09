<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\RssItem;
use App\Services\AstroBotPublisher;
use App\Services\AstroBotRssRefreshService;
use App\Services\RssFetchService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AstroBotController extends Controller
{
    public function __construct(
        private RssFetchService $fetchService,
        private AstroBotPublisher $publisher,
        private AstroBotRssRefreshService $rssRefreshService,
    ) {}

    /**
     * GET /api/admin/astrobot/items
     *
     * Query params:
     * - scope: today|all (default today)
     * - status: pending|approved|scheduled|published|discarded|error (comma-separated)
     * - source: string (optional)
     * - per_page: int (default 50)
     * - page: int
     *
     * Returns paginated list of RSS items.
     */
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

        // Scope
        $scope = $request->get('scope', 'today');
        if ($scope === 'today') {
            $query->today();
        }

        // Status filter
        if ($status = $request->get('status')) {
            $statuses = array_map('trim', explode(',', $status));
            $query->byStatus($statuses);
        }

        // Source filter
        if ($source = $request->get('source')) {
            $query->bySource($source);
        }

        // Search filter
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'LIKE', '%' . $search . '%')
                  ->orWhere('summary', 'LIKE', '%' . $search . '%');
            });
        }

        // Order: pending/approved/scheduled first, then published by published_at desc
        $query->orderByRaw("
            CASE status
                WHEN 'pending' THEN 1
                WHEN 'approved' THEN 2
                WHEN 'scheduled' THEN 3
                WHEN 'published' THEN 4
                WHEN 'discarded' THEN 5
                WHEN 'error' THEN 6
                ELSE 7
            END ASC,
            CASE WHEN status = 'published' AND published_at IS NOT NULL THEN published_at END DESC,
            fetched_at DESC
        ");

        $perPage = $request->get('per_page', 50);
        $items = $query->paginate($perPage, ['*'], 'page', $request->get('page'));

        return response()->json($items);
    }

    /**
     * POST /api/admin/astrobot/fetch
     *
     * Body:
     * - source: string (optional, default nasa_news)
     *
     * Returns: { created, skipped, errors }
     */
    public function fetch(Request $request): JsonResponse
    {
        $request->validate([
            'source' => 'string',
        ]);

        $source = $request->get('source', RssFetchService::SOURCE_NASA_NEWS);
        $result = $this->fetchService->fetch($source);

        return response()->json($result);
    }

    /**
     * POST /api/admin/astrobot/items/{id}/approve
     */
    public function approve(RssItem $item): JsonResponse
    {
        if ($item->status !== RssItem::STATUS_PENDING) {
            return response()->json(['message' => 'Only pending items can be approved.'], 422);
        }

        $item->update(['status' => RssItem::STATUS_APPROVED]);

        return response()->json(['message' => 'Item approved.']);
    }

    /**
     * POST /api/admin/astrobot/items/{id}/publish
     *
     * Body (optional):
     * - content: string (override generated content)
     * - summary: string (override summary)
     */
    public function publish(RssItem $item, Request $request): JsonResponse
    {
        $request->validate([
            'content' => 'string|max:5000',
            'summary' => 'string|max:500',
        ]);

        try {
            $overrides = $request->only(['content', 'summary']);
            $post = $this->publisher->publish($item, $overrides);
            return response()->json(['message' => 'Published.', 'post_id' => $post->id]);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /api/admin/astrobot/items/{id}/schedule
     *
     * Body:
     * - scheduled_for: string (ISO 8601 datetime)
     */
    public function schedule(RssItem $item, Request $request): JsonResponse
    {
        $request->validate([
            'scheduled_for' => 'required|date|after:now',
        ]);

        try {
            $when = Carbon::parse($request->get('scheduled_for'));
            $this->publisher->schedule($item, $when);
            return response()->json(['message' => 'Item scheduled.', 'scheduled_for' => $when->toIso8601String()]);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /api/admin/astrobot/items/{id}/discard
     *
     * Body (optional):
     * - reason: string
     */
    public function discard(RssItem $item, Request $request): JsonResponse
    {
        $request->validate([
            'reason' => 'string|max:500',
        ]);

        $this->publisher->discard($item, $request->get('reason'));
        return response()->json(['message' => 'Item discarded.']);
    }

    /**
     * GET /api/admin/astrobot/posts
     *
     * Query params:
     * - scope: today|all (default today)
     * - per_page: int (default 50)
     * - page: int
     *
     * Returns paginated list of AstroBot published posts.
     */
    public function posts(Request $request): JsonResponse
    {
        $request->validate([
            'scope' => 'in:today,all',
            'per_page' => 'integer|min:1|max:200',
            'page' => 'integer|min:1',
        ]);

        $query = Post::query()
            ->where('source_name', 'astrobot')
            ->with('user:id,name,email');

        // Scope
        $scope = $request->get('scope', 'today');
        if ($scope === 'today') {
            $query->whereDate('created_at', now()->toDateString());
        }

        $query->orderBy('created_at', 'desc');

        $perPage = $request->get('per_page', 50);
        $posts = $query->paginate($perPage, ['*'], 'page', $request->get('page'));

        return response()->json($posts);
    }

    /**
     * DELETE /api/admin/astrobot/posts/{id}
     *
     * Soft delete (hide) a published AstroBot post.
     */
    public function deletePost(Post $post): JsonResponse
    {
        if ($post->source_name !== 'astrobot') {
            return response()->json(['message' => 'Only AstroBot posts can be deleted via this endpoint.'], 403);
        }

        // Soft delete if supported, otherwise hard delete
        if (method_exists($post, 'trashed')) {
            $post->delete();
        } else {
            $post->forceDelete();
        }

        return response()->json(['message' => 'Post hidden.']);
    }

    /**
     * PUT /api/admin/astrobot/items/{id}
     *
     * Body:
     * - title: string
     * - summary: string
     */
    public function update(RssItem $item, Request $request): JsonResponse
    {
        $request->validate([
            'title' => 'required|string|max:500',
            'summary' => 'nullable|string|max:2000',
        ]);

        $item->update([
            'title' => $request->get('title'),
            'summary' => $request->get('summary'),
        ]);

        return response()->json(['message' => 'Item updated.']);
    }

    /**
     * POST /api/admin/astrobot/bulk
     *
     * Body:
     * - action: approve|publish|discard
     * - item_ids: array
     * - reason: string (optional, for discard)
     */
    public function bulk(Request $request): JsonResponse
    {
        $request->validate([
            'action' => 'required|in:approve,publish,discard',
            'item_ids' => 'required|array|min:1',
            'item_ids.*' => 'integer',
            'reason' => 'nullable|string|max:500',
        ]);

        $action = $request->get('action');
        $itemIds = $request->get('item_ids');
        $reason = $request->get('reason');

        $items = RssItem::whereIn('id', $itemIds)->get();
        $processed = 0;
        $errors = 0;

        foreach ($items as $item) {
            try {
                switch ($action) {
                    case 'approve':
                        if ($item->status === RssItem::STATUS_PENDING) {
                            $item->update(['status' => RssItem::STATUS_APPROVED]);
                            $processed++;
                        }
                        break;

                    case 'publish':
                        if ($item->canPublish()) {
                            $this->publisher->publish($item);
                            $processed++;
                        }
                        break;

                    case 'discard':
                        $this->publisher->discard($item, $reason);
                        $processed++;
                        break;
                }
            } catch (\Throwable $e) {
                $errors++;
                // Continue processing other items
            }
        }

        return response()->json([
            'message' => "Bulk {$action} completed.",
            'processed' => $processed,
            'errors' => $errors,
        ]);
    }

    /**
     * POST /api/admin/astrobot/publish-scheduled
     *
     * Manually trigger publishing of scheduled items whose time has come.
     */
    public function publishScheduled(): JsonResponse
    {
        $count = $this->publisher->publishScheduled();
        return response()->json(['message' => "Published {$count} scheduled items."]);
    }

    /**
     * POST /api/admin/astrobot/rss/refresh
     *
     * Body:
     * - source: string (optional, default nasa_news)
     */
    public function refreshRss(Request $request): JsonResponse
    {
        $request->validate([
            'source' => 'string',
        ]);

        $source = (string) $request->get('source', RssFetchService::SOURCE_NASA_NEWS);
        $result = $this->rssRefreshService->refresh($source);

        return response()->json([
            'message' => 'RSS refresh completed.',
            'result' => $result,
        ]);
    }
}
