<?php

namespace App\Services;

use App\Models\BlogPost;
use App\Models\Tag;
use App\Services\Storage\MediaStorageService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Throwable;

class AdminBlogPostService
{
    public function __construct(
        private readonly MediaStorageService $mediaStorage,
    ) {
    }

    public function list(?string $status, ?string $search = null, int $perPage = 10): LengthAwarePaginator
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

        $normalizedSearch = trim((string) $search);
        if ($normalizedSearch !== '') {
            $like = '%' . $normalizedSearch . '%';
            $query->where(function (Builder $builder) use ($like): void {
                $builder->where('title', 'like', $like)
                    ->orWhere('content', 'like', $like)
                    ->orWhereHas('tags', function (Builder $tagQuery) use ($like): void {
                        $tagQuery->where('name', 'like', $like);
                    })
                    ->orWhereHas('user', function (Builder $userQuery) use ($like): void {
                        $userQuery
                            ->where('name', 'like', $like)
                            ->orWhere('email', 'like', $like);
                    });
            });
        }

        $safePerPage = max(5, min($perPage, 50));

        return $query->paginate($safePerPage);
    }

    public function create(
        array $validated,
        int $userId,
        ?UploadedFile $coverImage,
        ?array &$tagSync = null
    ): BlogPost
    {
        $tagSync = null;
        $data = [
            'user_id' => $userId,
            'title' => $validated['title'],
            'slug' => $this->uniqueSlug($validated['title']),
            'content' => $validated['content'],
            'published_at' => $validated['published_at'] ?? null,
        ];

        if ($coverImage) {
            $path = $this->mediaStorage->storeBlogCover($coverImage, $userId);
            $data['cover_image_path'] = $path;
            $data['cover_image_mime'] = $coverImage->getClientMimeType();
            $data['cover_image_original_name'] = $coverImage->getClientOriginalName();
            $data['cover_image_size'] = $coverImage->getSize();
        }

        $blogPost = BlogPost::create($data);

        if (array_key_exists('tag_ids', $validated)) {
            $tagSync = $this->syncTagIds($blogPost, $validated['tag_ids'] ?? []);
        } elseif (array_key_exists('tags', $validated)) {
            $tagSync = $this->syncTags($blogPost, $validated['tags'] ?? []);
        }

        return $blogPost->load(['user:id,name,email,is_admin', 'tags:id,name,slug']);
    }

    public function update(
        BlogPost $blogPost,
        array $validated,
        ?UploadedFile $coverImage,
        ?array &$tagSync = null
    ): BlogPost
    {
        $tagSync = null;
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
            $this->mediaStorage->delete($blogPost->cover_image_path);

            $path = $this->mediaStorage->storeBlogCover($coverImage, (int) $blogPost->user_id);
            $blogPost->update([
                'cover_image_path' => $path,
                'cover_image_mime' => $coverImage->getClientMimeType(),
                'cover_image_original_name' => $coverImage->getClientOriginalName(),
                'cover_image_size' => $coverImage->getSize(),
            ]);
        }

        if (array_key_exists('tag_ids', $validated)) {
            $tagSync = $this->syncTagIds($blogPost, $validated['tag_ids'] ?? []);
        } elseif (array_key_exists('tags', $validated)) {
            $tagSync = $this->syncTags($blogPost, $validated['tags'] ?? []);
        }

        return $blogPost->load(['user:id,name,email,is_admin', 'tags:id,name,slug']);
    }

    public function delete(BlogPost $blogPost): void
    {
        $this->mediaStorage->delete($blogPost->cover_image_path);

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

    /**
     * @param array<int,mixed> $tags
     * @return array{attached_existing:int,created_new:int,added_total:int,selected_total:int}
     */
    private function syncTags(BlogPost $blogPost, array $tags): array
    {
        $currentTagIds = $blogPost->tags()
            ->pluck('tags.id')
            ->map(static fn ($id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->values()
            ->all();

        $existingTagsByKey = $this->loadExistingTagsByNormalizedKey();
        $normalizedNameToDisplay = [];
        foreach ($tags as $tagName) {
            $name = $this->normalizeTagName($tagName);
            if ($name === '') {
                continue;
            }

            $key = $this->normalizeTagKey($name);
            if ($key === '' || isset($normalizedNameToDisplay[$key])) {
                continue;
            }

            $normalizedNameToDisplay[$key] = $name;
        }

        $ids = [];
        $createdTagIds = [];

        foreach ($normalizedNameToDisplay as $key => $name) {
            if (isset($existingTagsByKey[$key])) {
                $ids[] = (int) ($existingTagsByKey[$key]->id ?? 0);
                continue;
            }

            $tag = Tag::query()->create([
                'name' => $name,
                'slug' => Str::slug($name),
            ]);

            $existingTagsByKey[$key] = $tag;
            $ids[] = (int) $tag->id;
            $createdTagIds[(int) $tag->id] = true;
        }

        $targetIds = array_values(array_unique(array_filter(array_map(
            static fn (mixed $id): int => (int) $id,
            $ids
        ), static fn (int $id): bool => $id > 0)));
        $blogPost->tags()->sync($targetIds);

        $attachedIds = array_values(array_diff($targetIds, $currentTagIds));
        $createdNew = 0;
        foreach ($attachedIds as $tagId) {
            if (isset($createdTagIds[(int) $tagId])) {
                $createdNew++;
            }
        }

        return [
            'attached_existing' => max(0, count($attachedIds) - $createdNew),
            'created_new' => max(0, $createdNew),
            'added_total' => count($attachedIds),
            'selected_total' => count($targetIds),
        ];
    }

    /**
     * @param array<int,mixed> $tagIds
     * @return array{attached_existing:int,created_new:int,added_total:int,selected_total:int}
     */
    private function syncTagIds(BlogPost $blogPost, array $tagIds): array
    {
        $currentTagIds = $blogPost->tags()
            ->pluck('tags.id')
            ->map(static fn ($id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->values()
            ->all();

        $ids = collect($tagIds)
            ->map(static fn (mixed $value): int => (int) $value)
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        $blogPost->tags()->sync($ids);

        $attachedIds = array_values(array_diff($ids, $currentTagIds));
        $attachedCount = count($attachedIds);

        return [
            'attached_existing' => $attachedCount,
            'created_new' => 0,
            'added_total' => $attachedCount,
            'selected_total' => count($ids),
        ];
    }

    private function normalizeTagName(mixed $name): string
    {
        $normalized = trim((string) $name);
        if ($normalized === '') {
            return '';
        }

        return strlen($normalized) > 255 ? '' : $normalized;
    }

    /**
     * @return array<string,Tag>
     */
    private function loadExistingTagsByNormalizedKey(): array
    {
        $result = [];
        foreach (Tag::query()->orderBy('id')->get(['id', 'name', 'slug']) as $tag) {
            $key = $this->normalizeTagKey((string) $tag->name);
            if ($key === '' || isset($result[$key])) {
                continue;
            }

            $result[$key] = $tag;
        }

        return $result;
    }

    private function normalizeTagKey(string $name): string
    {
        $trimmed = trim($name);
        if ($trimmed === '') {
            return '';
        }

        $collapsed = preg_replace('/\s+/u', ' ', $trimmed) ?? $trimmed;
        $withoutDiacritics = $this->removeDiacritics($collapsed);

        return function_exists('mb_strtolower')
            ? mb_strtolower($withoutDiacritics, 'UTF-8')
            : strtolower($withoutDiacritics);
    }

    private function removeDiacritics(string $value): string
    {
        if (class_exists(\Normalizer::class)) {
            try {
                $normalized = \Normalizer::normalize($value, \Normalizer::FORM_D);
            } catch (Throwable) {
                $normalized = false;
            }

            if (is_string($normalized) && $normalized !== '') {
                $stripped = preg_replace('/\p{Mn}+/u', '', $normalized);
                if (is_string($stripped) && $stripped !== '') {
                    return $stripped;
                }
            }
        }

        if (function_exists('iconv')) {
            $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            if (is_string($transliterated) && $transliterated !== '') {
                return $transliterated;
            }
        }

        return $value;
    }
}
