<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Hashtag;
use App\Services\PollService;
use App\Services\PostPayloadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HashtagController extends Controller
{
    public function __construct(
        private readonly PollService $polls,
        private readonly PostPayloadService $payloads,
    ) {
    }

    /**
     * GET /api/hashtags
     * List hashtags.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        $limit = $request->get('limit', 50);

        $hashtags = Hashtag::query()
            ->withCount('posts')
            ->orderBy('posts_count', 'desc')
            ->paginate($limit);

        return response()->json($hashtags);
    }

    /**
     * GET /api/hashtags/{name}/posts
     * Posts for a hashtag.
     */
    public function posts(Request $request, string $name): JsonResponse
    {
        $request->validate([
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        $limit = $request->get('limit', 20);

        $hashtag = Hashtag::where('name', $name)->firstOrFail();
        $viewer = $request->user() ?? $request->user('sanctum');

        $posts = $hashtag->posts()
            ->whereNull('parent_id')
            ->publiclyVisible()
            ->notExpired()
            ->with(array_merge(
                ['user:id,name,username,avatar_path,avatar_mode,avatar_color,avatar_icon,avatar_seed', 'hashtags'],
                $this->polls->pollRelations($viewer?->id)
            ))
            ->withCount(['likes', 'replies'])
            ->latest()
            ->paginate($limit);

        return response()->json($this->payloads->serializePaginator($posts, $viewer));
    }

    /**
     * GET /api/trending
     * Trending hashtags for the last 24 hours.
     */
    public function trending(Request $request): JsonResponse
    {
        $request->validate([
            'limit' => 'nullable|integer|min:1|max:20',
        ]);

        $limit = $request->get('limit', 10);
        $since = now()->subDay();

        $trending = Hashtag::query()
            ->whereHas('posts', static function ($query) use ($since) {
                $query->whereNull('posts.parent_id')
                    ->where('posts.created_at', '>=', $since)
                    ->publiclyVisible()
                    ->notExpired();
            })
            ->withCount([
                'posts as posts_count' => static function ($query) use ($since) {
                    $query->whereNull('posts.parent_id')
                        ->where('posts.created_at', '>=', $since)
                        ->publiclyVisible()
                        ->notExpired();
                },
            ])
            ->orderByDesc('posts_count')
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'name'])
            ->values()
            ->map(static fn (Hashtag $hashtag, int $index): array => [
                'id' => (int) $hashtag->id,
                'name' => (string) $hashtag->name,
                'posts_count' => (int) $hashtag->posts_count,
                'rank' => $index + 1,
                'window_hours' => 24,
            ]);

        return response()->json($trending->values());
    }
}