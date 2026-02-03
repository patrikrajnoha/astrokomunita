<?php

namespace App\Services;

use App\Models\BlogPost;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Pagination\LengthAwarePaginator;

class BlogService
{
    /**
     * Get paginated blog posts with optional filters
     */
    public function getBlogPostsPaginated(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = BlogPost::query()
            ->with(['tags', 'user'])
            ->orderBy('published_at', 'desc');

        // Apply filters
        if (isset($filters['published'])) {
            if ($filters['published']) {
                $query->published();
            } else {
                $query->whereNull('published_at')->orWhere('published_at', '>', now());
            }
        }

        if (isset($filters['tag'])) {
            $tag = $filters['tag'];
            $query->whereHas('tags', function ($q) use ($tag) {
                $q->where('slug', $tag);
            });
        }

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('content', 'like', "%{$search}%");
            });
        }

        if (isset($filters['date_from'])) {
            $query->whereDate('published_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->whereDate('published_at', '<=', $filters['date_to']);
        }

        return $query->paginate($perPage);
    }

    /**
     * Create a new blog post
     */
    public function createBlogPost(array $data): BlogPost
    {
        return DB::transaction(function () use ($data) {
            $blogPost = BlogPost::create([
                'title' => $data['title'],
                'slug' => $this->generateUniqueSlug($data['title']),
                'content' => $data['content'],
                'cover_image_path' => $data['cover_image_path'] ?? null,
                'cover_image_mime' => $data['cover_image_mime'] ?? null,
                'cover_image_original_name' => $data['cover_image_original_name'] ?? null,
                'cover_image_size' => $data['cover_image_size'] ?? null,
                'published_at' => $data['published_at'] ?? null,
                'user_id' => $data['user_id'] ?? null,
            ]);

            // Attach tags if provided
            if (isset($data['tags']) && is_array($data['tags'])) {
                $this->syncTags($blogPost, $data['tags']);
            }

            return $blogPost->load(['tags', 'user']);
        });
    }

    /**
     * Update an existing blog post
     */
    public function updateBlogPost(BlogPost $blogPost, array $data): BlogPost
    {
        return DB::transaction(function () use ($blogPost, $data) {
            $updateData = [
                'title' => $data['title'] ?? $blogPost->title,
                'content' => $data['content'] ?? $blogPost->content,
                'cover_image_path' => $data['cover_image_path'] ?? $blogPost->cover_image_path,
                'cover_image_mime' => $data['cover_image_mime'] ?? $blogPost->cover_image_mime,
                'cover_image_original_name' => $data['cover_image_original_name'] ?? $blogPost->cover_image_original_name,
                'cover_image_size' => $data['cover_image_size'] ?? $blogPost->cover_image_size,
            ];

            // Update slug if title changed
            if (isset($data['title']) && $data['title'] !== $blogPost->title) {
                $updateData['slug'] = $this->generateUniqueSlug($data['title'], $blogPost->id);
            }

            // Update published_at if provided
            if (isset($data['published_at'])) {
                $updateData['published_at'] = $data['published_at'];
            }

            $blogPost->update($updateData);

            // Sync tags if provided
            if (isset($data['tags']) && is_array($data['tags'])) {
                $this->syncTags($blogPost, $data['tags']);
            }

            return $blogPost->load(['tags', 'user']);
        });
    }

    /**
     * Delete a blog post
     */
    public function deleteBlogPost(BlogPost $blogPost): bool
    {
        return DB::transaction(function () use ($blogPost) {
            // Detach all tags
            $blogPost->tags()->detach();
            
            return $blogPost->delete();
        });
    }

    /**
     * Get published blog posts
     */
    public function getPublishedPosts(int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        return BlogPost::query()
            ->published()
            ->with(['tags', 'user'])
            ->orderBy('published_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get recent blog posts (simplified since no featured field)
     */
    public function getRecentPosts(int $limit = 5): \Illuminate\Database\Eloquent\Collection
    {
        return BlogPost::query()
            ->published()
            ->with(['tags', 'user'])
            ->orderBy('published_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get related blog posts
     */
    public function getRelatedPosts(BlogPost $blogPost, int $limit = 5): \Illuminate\Database\Eloquent\Collection
    {
        return BlogPost::query()
            ->where('id', '!=', $blogPost->id)
            ->published()
            ->whereHas('tags', function ($query) use ($blogPost) {
                $query->whereIn('tags.id', $blogPost->tags->pluck('id'));
            })
            ->with(['tags', 'user'])
            ->orderBy('published_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Publish a blog post
     */
    public function publishBlogPost(BlogPost $blogPost): BlogPost
    {
        $blogPost->published_at = now();
        $blogPost->save();

        return $blogPost;
    }

    /**
     * Get blog post statistics
     */
    public function getBlogStatistics(): array
    {
        return [
            'total' => BlogPost::count(),
            'published' => BlogPost::published()->count(),
            'draft' => BlogPost::whereNull('published_at')->orWhere('published_at', '>', now())->count(),
            'published_this_month' => BlogPost::published()
                ->whereMonth('published_at', now()->month)
                ->whereYear('published_at', now()->year)
                ->count(),
        ];
    }

    /**
     * Generate unique slug
     */
    private function generateUniqueSlug(string $title, int $excludeId = null): string
    {
        $slug = Str::slug($title);
        $originalSlug = $slug;
        $counter = 1;

        $query = BlogPost::where('slug', $slug);
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        while ($query->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $query = BlogPost::where('slug', $slug);
            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }
            $counter++;
        }

        return $slug;
    }

    /**
     * Sync tags to blog post
     */
    private function syncTags(BlogPost $blogPost, array $tagNames): void
    {
        $tagIds = [];

        foreach ($tagNames as $tagName) {
            // Normalize and validate tag name
            $name = $this->normalizeTagName($tagName);
            if ($name === '') {
                continue;
            }

            $tag = Tag::firstOrCreate(
                ['name' => $name],
                ['slug' => Str::slug($name)]
            );
            $tagIds[] = $tag->id;
        }

        $blogPost->tags()->sync($tagIds);
    }

    /**
     * Normalize tag name to ensure it's a valid string.
     */
    private function normalizeTagName($name): string
    {
        // Cast to string and trim whitespace
        $normalized = trim((string) $name);
        
        // Validate tag name is not empty
        if (empty($normalized)) {
            return '';
        }
        
        // Ensure it's not too long (database constraint)
        if (strlen($normalized) > 255) {
            return '';
        }
        
        return $normalized;
    }
}
