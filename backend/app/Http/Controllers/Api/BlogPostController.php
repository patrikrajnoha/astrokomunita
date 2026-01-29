<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use Illuminate\Http\Request;

class BlogPostController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'tag' => ['nullable', 'string', 'max:100'],
            'q' => ['nullable', 'string', 'max:200'],
        ]);

        $tag = $validated['tag'] ?? null;
        $q = isset($validated['q']) ? trim($validated['q']) : null;

        $items = BlogPost::query()
            ->published()
            ->with(['user:id,name,email,is_admin', 'tags:id,name,slug'])
            ->withCount('comments')
            ->when($tag, function ($q) use ($tag) {
                $q->whereHas('tags', fn ($t) => $t->where('slug', $tag));
            })
            ->when($q !== null && $q !== '', function ($qq) use ($q) {
                $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
                $qq->where(function ($sub) use ($like) {
                    $sub->where('title', 'like', $like)
                        ->orWhere('content', 'like', $like);
                });
            })
            ->orderByDesc('published_at')
            ->paginate(10);

        return response()->json($items);
    }

    public function show(string $slug)
    {
        $blogPost = $this->resolvePublished($slug);

        if (!$blogPost) {
            return response()->json([
                'message' => 'Not found',
            ], 404);
        }

        $blogPost->increment('views');

        $blogPost->load(['user:id,name,email,is_admin', 'tags:id,name,slug']);
        $blogPost->loadCount('comments');

        return response()->json($blogPost);
    }

    public function related(string $slug)
    {
        $blogPost = $this->resolvePublished($slug);

        if (!$blogPost) {
            return response()->json([
                'message' => 'Not found',
            ], 404);
        }

        $tagIds = $blogPost->tags()->pluck('blog_tags.id')->all();

        if (empty($tagIds)) {
            return response()->json([]);
        }

        $items = BlogPost::query()
            ->published()
            ->where('id', '!=', $blogPost->id)
            ->whereHas('tags', fn ($q) => $q->whereIn('blog_tags.id', $tagIds))
            ->with(['user:id,name,email,is_admin', 'tags:id,name,slug'])
            ->withCount([
                'tags as matching_tags_count' => fn ($q) => $q->whereIn('blog_tags.id', $tagIds),
            ])
            ->orderByDesc('matching_tags_count')
            ->orderByDesc('published_at')
            ->limit(4)
            ->get();

        return response()->json($items);
    }

    private function resolvePublished(string $slug): ?BlogPost
    {
        $query = BlogPost::query()->published();

        if (ctype_digit($slug)) {
            return $query->where('id', (int) $slug)->first();
        }

        return $query->where('slug', $slug)->first();
    }
}
