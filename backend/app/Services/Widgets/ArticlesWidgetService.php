<?php

namespace App\Services\Widgets;

use App\Models\BlogPost;
use App\Services\Storage\MediaStorageService;
use Illuminate\Support\Facades\Cache;

class ArticlesWidgetService
{
    public function __construct(
        private readonly MediaStorageService $mediaStorageService,
    ) {
    }

    /**
     * @return array<string,mixed>
     */
    public function payload(): array
    {
        $ttlSeconds = max((int) config('widgets.articles_widget.cache_ttl_seconds', 60), 1);
        $cacheKey = 'articles_widget_v1';

        return Cache::remember($cacheKey, now()->addSeconds($ttlSeconds), function (): array {
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
                'most_read' => $this->mapWidgetItems($mostRead),
                'latest' => $this->mapWidgetItems($latest),
                'generated_at' => now()->toIso8601String(),
            ];
        });
    }

    /**
     * @param  \Illuminate\Support\Collection<int,\App\Models\BlogPost>  $items
     * @return array<int,array<string,mixed>>
     */
    private function mapWidgetItems($items): array
    {
        return $items
            ->map(fn (BlogPost $post) => [
                'id' => $post->id,
                'title' => $post->title,
                'slug' => $post->slug,
                'thumbnail_url' => $this->mediaStorageService->absoluteUrl($post->cover_image_path),
                'views' => (int) $post->views,
                'created_at' => optional($post->created_at)?->toIso8601String(),
            ])
            ->values()
            ->all();
    }
}
