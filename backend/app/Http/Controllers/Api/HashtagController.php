<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Hashtag;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class HashtagController extends Controller
{
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

        $posts = $hashtag->posts()
            ->whereNull('parent_id') // Len root posts
            ->publiclyVisible()
            ->notExpired()
            ->with(['user:id,name,username,avatar_path', 'hashtags'])
            ->withCount(['likes', 'replies'])
            ->latest()
            ->paginate($limit);

        return response()->json($posts);
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
