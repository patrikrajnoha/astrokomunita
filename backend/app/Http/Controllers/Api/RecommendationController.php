<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class RecommendationController extends Controller
{
    /**
     * GET /api/recommendations/users
     * Odporúčané účty - používatelia s najviac postmi za posledných 7 dní.
     */
    public function users(Request $request): JsonResponse
    {
        $request->validate([
            'limit' => 'nullable|integer|min:1|max:20',
        ]);

        $limit = $request->get('limit', 10);
        $currentUser = $request->user();

        $users = User::query()
            ->where('is_active', true)
            ->where('is_banned', false)
            ->where('is_bot', false) // Neodporúčame bot účty
            ->when($currentUser, function ($query) use ($currentUser) {
                $query->where('id', '!=', $currentUser->id); // Vylúčiť prihláseného užívateľa
            })
            ->whereHas('posts', function ($query) {
                $query->where('created_at', '>=', now()->subDays(7))
                      ->whereNull('parent_id') // Len root posts
                      ->where('is_hidden', false);
            })
            ->withCount(['posts' => function ($query) {
                $query->where('created_at', '>=', now()->subDays(7))
                      ->whereNull('parent_id')
                      ->where('is_hidden', false);
            }])
            ->orderBy('posts_count', 'desc')
            ->limit($limit)
            ->get(['id', 'name', 'username', 'avatar_path', 'posts_count']);

        return response()->json($users);
    }

    /**
     * GET /api/recommendations/posts
     * Odporúčané príspevky - príspevky s najviac likes za posledných 48 hodín.
     */
    public function posts(Request $request): JsonResponse
    {
        $request->validate([
            'limit' => 'nullable|integer|min:1|max:20',
        ]);

        $limit = $request->get('limit', 10);

        $posts = Post::query()
            ->whereNull('parent_id') // Len root posts
            ->where('is_hidden', false)
            ->notExpired()
            ->where('created_at', '>=', now()->subHours(48))
            ->with(['user:id,name,username,avatar_path', 'hashtags'])
            ->withCount(['likes', 'replies'])
            ->having('likes_count', '>', 0)
            ->orderBy('likes_count', 'desc')
            ->limit($limit)
            ->get();

        return response()->json($posts);
    }
}
