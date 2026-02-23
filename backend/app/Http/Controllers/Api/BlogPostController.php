<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Services\Storage\MediaStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

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

        $tagIds = $blogPost->tags()->pluck('tags.id')->all();

        if (empty($tagIds)) {
            return response()->json([]);
        }

        $items = BlogPost::query()
            ->published()
            ->where('id', '!=', $blogPost->id)
            ->whereHas('tags', fn ($q) => $q->whereIn('tags.id', $tagIds))
            ->with(['user:id,name,email,is_admin', 'tags:id,name,slug'])
            ->withCount([
                'tags as matching_tags_count' => fn ($q) => $q->whereIn('tags.id', $tagIds),
            ])
            ->orderByDesc('matching_tags_count')
            ->orderByDesc('published_at')
            ->limit(4)
            ->get();

        return response()->json($items);
    }

    public function widget(MediaStorageService $mediaStorageService)
    {
        $ttlSeconds = max((int) config('widgets.articles_widget.cache_ttl_seconds', 60), 1);
        $cacheKey = 'articles_widget_v1';

        $payload = Cache::remember($cacheKey, now()->addSeconds($ttlSeconds), function () use ($mediaStorageService) {
            $latest = BlogPost::query()
                ->published()
                ->select(['id', 'title', 'slug', 'cover_image_path', 'views', 'created_at'])
                ->orderByDesc('created_at')
                ->limit(3)
                ->get();

            $mostRead = BlogPost::query()
                ->published()
                ->select(['id', 'title', 'slug', 'cover_image_path', 'views', 'created_at'])
                ->orderByDesc('views')
                ->orderByDesc('created_at')
                ->limit(3)
                ->get();

            return [
                'most_read' => $this->mapWidgetItems($mostRead, $mediaStorageService),
                'latest' => $this->mapWidgetItems($latest, $mediaStorageService),
                'generated_at' => now()->toIso8601String(),
            ];
        });

        return response()->json($payload);
    }

    private function resolvePublished(string $slug): ?BlogPost
    {
        $query = BlogPost::query()->published();

        // Validácia a sanitizácia slugu
        $slug = trim($slug);
        if (empty($slug)) {
            return null;
        }

        // Ak je to číslo, hľadaj podľa ID, inak podľa slugu
        if (ctype_digit($slug)) {
            $id = (int) $slug;
            return $query->where('id', $id)->first();
        }

        // Ochrana proti SQL injection - povoliť len validné znaky pre slug
        if (!preg_match('/^[a-z0-9\-_]+$/', $slug)) {
            return null;
        }

        return $query->where('slug', $slug)->first();
    }

    private function mapWidgetItems($items, MediaStorageService $mediaStorageService): array
    {
        return $items
            ->map(fn (BlogPost $post) => [
                'id' => $post->id,
                'title' => $post->title,
                'slug' => $post->slug,
                'thumbnail_url' => $mediaStorageService->absoluteUrl($post->cover_image_path),
                'views' => (int) $post->views,
                'created_at' => optional($post->created_at)?->toIso8601String(),
            ])
            ->values()
            ->all();
    }
}
