<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Hashtag;
use App\Services\PollService;
use App\Services\PostPayloadService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class HashtagController extends Controller
{
    public function __construct(
        private readonly PollService $polls,
        private readonly PostPayloadService $payloads,
    ) {
    }

    /**
     * GET /api/hashtags
     * Zoznam všetkých hashtagov.
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
     * Príspevky s daným hashtagom.
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
            ->whereNull('parent_id') // Len root posts
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
     * Trending hashtagy za posledných 24 hodín.
     */
    public function trending(Request $request): JsonResponse
    {
        $request->validate([
            'limit' => 'nullable|integer|min:1|max:20',
        ]);

        $limit = $request->get('limit', 10);

        // Zjednodušené query pre trending
        $trending = Hashtag::query()
            ->limit($limit)
            ->get(['id', 'name']);

        return response()->json($trending);
    }
}
