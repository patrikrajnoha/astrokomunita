<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tag;
use App\Models\Post;
use Illuminate\Http\Request;

class TagController extends Controller
{
    /**
     * GET /api/tags/suggest?q=per&limit=8
     * 
     * Tag autocomplete endpoint.
     * Accepts q with/without leading '#'.
     * Returns array of objects: [{ name: "perseids", count: 123 }, ...]
     * Sorting: tags starting with q first, then by count desc, then name asc.
     */
    public function suggest(Request $request)
    {
        $q = $request->query('q', '');
        $limit = min((int) $request->query('limit', 8), 50);

        // Remove leading # if present
        $q = ltrim($q, '#');
        
        // Validate query: 1..32 chars, allowed [a-z0-9_]
        if (strlen($q) < 1 || strlen($q) > 32 || !preg_match('/^[a-z0-9_]+$/i', $q)) {
            return response()->json([]);
        }

        $q = strtolower($q);

        // Get tags with post counts, efficiently sorted
        $tags = Tag::withCount('posts')
            ->where('name', 'like', $q . '%')
            ->orderByRaw('
                CASE 
                    WHEN name LIKE ? THEN 1 
                    ELSE 2 
                END,
                posts_count DESC,
                name ASC
            ', [$q])
            ->limit($limit)
            ->get(['name', 'posts_count']);

        return response()->json($tags->map(fn ($tag) => [
            'name' => $tag->name,
            'count' => (int) $tag->posts_count,
        ]));
    }

    /**
     * GET /api/tags/{tag}
     * 
     * Get posts by tag name or slug.
     * Returns paginated posts with the specified tag.
     */
    public function show(Request $request, $tag)
    {
        // Normalize tag name
        $tag = strtolower($tag);
        
        // Find tag by name or slug
        $tagModel = Tag::where('name', $tag)
            ->orWhere('slug', $tag)
            ->first();

        if (!$tagModel) {
            return response()->json([
                'message' => 'Tag not found',
                'tag' => $tag
            ], 404);
        }

        // Get posts with this tag
        $perPage = (int) $request->query('per_page', 20);
        $perPage = max(1, min($perPage, 50)); // Clamp between 1-50

        $posts = Post::query()
            ->whereHas('tags', function ($q) use ($tagModel) {
                $q->where('tags.id', $tagModel->id);
            })
            ->with([
                'user:id,name,username,email,location,bio,is_admin,avatar_path',
                'tags:id,name',
                'replies.user:id,name,username,email,location,bio,is_admin,avatar_path',
                'parent.user:id,name,username,email,location,bio,is_admin,avatar_path',
            ])
            ->withCount(['likes', 'replies'])
            ->where('is_hidden', false)
            ->notExpired() // Exclude expired AstroBot posts
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'tag' => [
                'name' => $tagModel->name,
                'slug' => $tagModel->slug,
                'posts_count' => $tagModel->posts()->count()
            ],
            'posts' => $posts
        ]);
    }
}
