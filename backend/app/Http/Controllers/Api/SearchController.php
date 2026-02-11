<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    /**
     * Unified search suggestions for autocomplete.
     */
    public function suggest(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:2|max:255',
            'limit' => 'nullable|integer|min:1|max:10',
        ]);

        $query = trim((string) $request->get('q', ''));
        $limit = (int) $request->get('limit', 8);
        $limit = max(1, min($limit, 10));

        if ($query === '' || mb_strlen($query) < 2) {
            return response()->json(['data' => []]);
        }

        $usersLimit = min(5, $limit);
        $users = User::query()
            ->where('is_active', true)
            ->where('is_banned', false)
            ->where(function ($q) use ($query) {
                $q->where('username', 'LIKE', $query . '%')
                    ->orWhere('name', 'LIKE', $query . '%');
            })
            ->select(['id', 'name', 'username'])
            ->orderBy('username')
            ->limit($usersLimit)
            ->get();

        $remaining = max(0, $limit - $users->count());
        $tags = collect();

        if ($remaining > 0) {
            $tags = Tag::query()
                ->where('name', 'LIKE', $query . '%')
                ->withCount('posts')
                ->orderByDesc('posts_count')
                ->orderBy('name')
                ->limit($remaining)
                ->get(['id', 'name']);
        }

        $suggestions = collect();

        foreach ($users as $user) {
            $displayName = trim((string) $user->name);
            $username = trim((string) $user->username);
            $label = $displayName !== ''
                ? sprintf('%s (@%s)', $displayName, $username)
                : '@' . $username;

            $suggestions->push([
                'id' => (string) $user->id,
                'type' => 'user',
                'label' => $label,
                'value' => $username,
            ]);
        }

        foreach ($tags as $tag) {
            $name = trim((string) $tag->name);
            $suggestions->push([
                'id' => (string) $tag->id,
                'type' => 'tag',
                'label' => '#' . $name,
                'value' => '#' . $name,
            ]);
        }

        return response()->json([
            'data' => $suggestions->take($limit)->values(),
        ]);
    }

    /**
     * Search users by username or name (prefix, case-insensitive).
     */
    public function users(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:1|max:255',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        $query = $request->get('q');
        $limit = $request->get('limit', 20);

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
            'total' => $users->count(),
        ]);
    }

    /**
     * Search root posts by content.
     */
    public function posts(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:1|max:255',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        $query = $request->get('q');
        $limit = $request->get('limit', 20);

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
            'total' => $posts->count(),
        ]);
    }
}
