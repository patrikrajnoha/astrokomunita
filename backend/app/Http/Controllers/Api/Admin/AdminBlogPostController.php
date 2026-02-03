<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdminBlogPostController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'status' => ['nullable', 'string', Rule::in(['published', 'draft', 'scheduled'])],
        ]);

        $status = $validated['status'] ?? null;

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

        return response()->json(
            $query->paginate(10)
        );
    }

    public function show(BlogPost $blogPost)
    {
        $blogPost->load(['user:id,name,email,is_admin', 'tags:id,name,slug']);

        return response()->json($blogPost);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'title' => ['required', 'string', 'min:3', 'max:180'],
            'content' => ['required', 'string', 'min:10'],
            'published_at' => ['nullable', 'date'],
            'cover_image' => ['nullable', 'file', 'max:5120', 'mimes:jpg,jpeg,png,webp'],
            'tags' => ['nullable', 'array', 'max:15'],
            'tags.*' => ['string', 'min:2', 'max:40'],
        ]);

        $data = [
            'user_id' => $user->id,
            'title' => $validated['title'],
            'slug' => $this->uniqueSlug($validated['title']),
            'content' => $validated['content'],
            'published_at' => $validated['published_at'] ?? null,
        ];

        if ($request->hasFile('cover_image')) {
            $file = $request->file('cover_image');
            $path = $file->store('blog-covers', 'public');

            $data['cover_image_path'] = $path;
            $data['cover_image_mime'] = $file->getClientMimeType();
            $data['cover_image_original_name'] = $file->getClientOriginalName();
            $data['cover_image_size'] = $file->getSize();
        }

        $blogPost = BlogPost::create($data);

        if (array_key_exists('tags', $validated)) {
            $this->syncTags($blogPost, $validated['tags']);
        }

        $blogPost->load(['user:id,name,email,is_admin', 'tags:id,name,slug']);

        return response()->json($blogPost, 201);
    }

    public function update(Request $request, BlogPost $blogPost)
    {
        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'min:3', 'max:180'],
            'content' => ['sometimes', 'required', 'string', 'min:10'],
            'published_at' => ['sometimes', 'nullable', 'date'],
            'cover_image' => ['sometimes', 'nullable', 'file', 'max:5120', 'mimes:jpg,jpeg,png,webp'],
            'tags' => ['sometimes', 'nullable', 'array', 'max:15'],
            'tags.*' => ['string', 'min:2', 'max:40'],
        ]);

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

        if (!empty($data)) {
            $blogPost->update($data);
        }

        if ($request->hasFile('cover_image')) {
            if ($blogPost->cover_image_path) {
                Storage::disk('public')->delete($blogPost->cover_image_path);
            }

            $file = $request->file('cover_image');
            $path = $file->store('blog-covers', 'public');

            $blogPost->update([
                'cover_image_path' => $path,
                'cover_image_mime' => $file->getClientMimeType(),
                'cover_image_original_name' => $file->getClientOriginalName(),
                'cover_image_size' => $file->getSize(),
            ]);
        }

        if ($request->has('tags')) {
            $this->syncTags($blogPost, $validated['tags'] ?? []);
        }

        $blogPost->load(['user:id,name,email,is_admin', 'tags:id,name,slug']);

        return response()->json($blogPost);
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
            // Normalize and validate tag name
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

    public function destroy(BlogPost $blogPost)
    {
        if ($blogPost->cover_image_path) {
            Storage::disk('public')->delete($blogPost->cover_image_path);
        }

        $blogPost->delete();

        return response()->json([
            'message' => 'Deleted',
        ]);
    }
}
