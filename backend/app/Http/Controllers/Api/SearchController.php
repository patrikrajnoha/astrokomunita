<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SearchController extends Controller
{
    /**
     * Vyhľadávanie používateľov podľa username alebo mena.
     * Case-insensitive prefix match.
     */
    public function users(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:1|max:255',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        $query = $request->get('q');
        $limit = $request->get('limit', 20);

        // Zjednodušené query bez hashtagov
        $users = User::query()
            ->where('is_active', true)
            ->where('is_banned', false)
            ->where(function ($q) use ($query) {
                $q->where('username', 'LIKE', $query . '%')
                  ->orWhere('name', 'LIKE', $query . '%');
            })
            ->select(['id', 'name', 'username', 'avatar_path'])
            ->orderBy('username')
            ->limit($limit)
            ->get();

        return response()->json([
            'data' => $users,
            'total' => $users->count()
        ]);
    }

    /**
     * Vyhľadávanie príspevkov podľa obsahu.
     * Len root posts (parent_id = null).
     */
    public function posts(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:1|max:255',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        $query = $request->get('q');
        $limit = $request->get('limit', 20);

        // Zjednodušené query bez hashtagov
        $posts = Post::query()
            ->whereNull('parent_id')
            ->where('is_hidden', false)
            ->where(function ($q) use ($query) {
                $q->where('content', 'LIKE', '%' . $query . '%');
            })
            ->with(['user:id,name,username,avatar_path'])
            ->withCount(['likes', 'replies'])
            ->latest()
            ->limit($limit)
            ->get();

        return response()->json([
            'data' => $posts,
            'total' => $posts->count()
        ]);
    }
}
