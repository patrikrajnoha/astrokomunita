<?php

namespace App\Services;

use App\Models\BlogPost;
use App\Models\Tag;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdminBlogPostService
{
    public function list(?string $status): LengthAwarePaginator
    {
        $query = BlogPost::query()
            ->with(['user:id,name,email,is_admin', 'tags:id,name,slug'])
            ->orderByDesc('created_at');

        if ($status === 'published') {
            $query->published();
        } elseif ($status === 'scheduled') {
            $query->whereNotNull('published_at')
                ->where('published_at', '>', now());
        } elseif ($status === 'draft') {
            $query->whereNull('published_at');
        }

        return $query->paginate(10);
    }

    public function create(array $validated, int $userId, ?UploadedFile $coverImage): BlogPost
    {
        $data = [
            'user_id' => $userId,
            'title' => $validated['title'],
            'slug' => $this->uniqueSlug($validated['title']),
            'content' => $validated['content'],
            'published_at' => $validated['published_at'] ?? null,
        ];

        if ($coverImage) {
            $path = $coverImage->store('blog-covers', 'public');
            $data['cover_image_path'] = $path;
            $data['cover_image_mime'] = $coverImage->getClientMimeType();
            $data['cover_image_original_name'] = $coverImage->getClientOriginalName();
            $data['cover_image_size'] = $coverImage->getSize();
        }

        $blogPost = BlogPost::create($data);

        if (array_key_exists('tags', $validated)) {
            $this->syncTags($blogPost, $validated['tags'] ?? []);
        }

        return $blogPost->load(['user:id,name,email,is_admin', 'tags:id,name,slug']);
    }

    public function update(BlogPost $blogPost, array $validated, ?UploadedFile $coverImage): BlogPost
    {
        $data = [];
        if (array_key_exists('title', $validated)) {
            $data['title'] = $validated['title'];
            if (empty($blogPost->slug)) {
                $data['slug'] = $this->uniqueSlug($validated['title']);
            }
        }
        if (array_key_exists('content', $validated)) {
            $data['content'] = $validated['content'];
        }
        if (array_key_exists('published_at', $validated)) {
            $data['published_at'] = $validated['published_at'];
        }

        if ($data !== []) {
            $blogPost->update($data);
        }

        if ($coverImage) {
            if ($blogPost->cover_image_path) {
                Storage::disk('public')->delete($blogPost->cover_image_path);
            }

            $path = $coverImage->store('blog-covers', 'public');
            $blogPost->update([
                'cover_image_path' => $path,
                'cover_image_mime' => $coverImage->getClientMimeType(),
                'cover_image_original_name' => $coverImage->getClientOriginalName(),
                'cover_image_size' => $coverImage->getSize(),
            ]);
        }

        if (array_key_exists('tags', $validated)) {
            $this->syncTags($blogPost, $validated['tags'] ?? []);
        }

        return $blogPost->load(['user:id,name,email,is_admin', 'tags:id,name,slug']);
    }

    public function delete(BlogPost $blogPost): void
    {
        if ($blogPost->cover_image_path) {
            Storage::disk('public')->delete($blogPost->cover_image_path);
        }

        $blogPost->delete();
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title);
        if ($base === '') {
            $base = 'clanok';
        }

        $slug = $base;
        $i = 2;
        while (BlogPost::query()->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i;
            $i++;
        }

        return $slug;
    }

    private function syncTags(BlogPost $blogPost, array $tags): void
    {
        $ids = [];
        foreach ($tags as $tagName) {
            $name = $this->normalizeTagName($tagName);
            if ($name === '') {
                continue;
            }

            $tag = Tag::firstOrCreate(
                ['name' => $name],
                ['slug' => Str::slug($name)]
            );

            $ids[] = $tag->id;
        }

        $blogPost->tags()->sync(array_values(array_unique($ids)));
    }

    private function normalizeTagName(mixed $name): string
    {
        $normalized = trim((string) $name);
        if ($normalized === '') {
            return '';
        }

        return strlen($normalized) > 255 ? '' : $normalized;
    }
}
