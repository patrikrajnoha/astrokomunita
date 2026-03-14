<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Services\AdminBlogPostService;
use App\Services\BlogTagSuggestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminBlogPostController extends Controller
{
    public function __construct(
        private readonly AdminBlogPostService $blogPosts,
        private readonly BlogTagSuggestionService $blogTagSuggestionService,
    ) {
    }

    public function index(Request $request)
    {
        $validated = $request->validate([
            'status' => ['nullable', 'string', Rule::in(['published', 'draft', 'scheduled'])],
            'q' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:50'],
        ]);

        return response()->json($this->blogPosts->list(
            $validated['status'] ?? null,
            $validated['q'] ?? null,
            (int) ($validated['per_page'] ?? 10),
        ));
    }

    public function show(BlogPost $blogPost)
    {
        return response()->json($blogPost->load(['user:id,name,email,is_admin', 'tags:id,name,slug']));
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
            'tag_ids' => ['nullable', 'array', 'max:15'],
            'tag_ids.*' => ['integer', 'distinct', Rule::exists('tags', 'id')],
        ]);

        $tagSync = null;
        $blogPost = $this->blogPosts->create(
            $validated,
            $user->id,
            $request->file('cover_image'),
            $tagSync
        );

        $payload = $blogPost->toArray();
        if (is_array($tagSync)) {
            $payload['tag_sync'] = $tagSync;
        }

        return response()->json($payload, 201);
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
            'tag_ids' => ['sometimes', 'nullable', 'array', 'max:15'],
            'tag_ids.*' => ['integer', 'distinct', Rule::exists('tags', 'id')],
        ]);

        $tagSync = null;
        $blogPost = $this->blogPosts->update(
            $blogPost,
            $validated,
            $request->file('cover_image'),
            $tagSync
        );

        $payload = $blogPost->toArray();
        if (is_array($tagSync)) {
            $payload['tag_sync'] = $tagSync;
        }

        return response()->json($payload);
    }

    public function destroy(BlogPost $blogPost)
    {
        $this->blogPosts->delete($blogPost);

        return response()->json([
            'message' => 'Vymazane',
        ]);
    }

    public function suggestTags(Request $request, BlogPost $blogPost): JsonResponse
    {
        $validated = $request->validate([
            'mode' => ['nullable', 'string', Rule::in([
                BlogTagSuggestionService::MODE_EXISTING_ONLY,
                BlogTagSuggestionService::MODE_ALLOW_NEW,
            ])],
        ]);

        $result = $this->blogTagSuggestionService->suggestForPost(
            $blogPost,
            (string) ($validated['mode'] ?? BlogTagSuggestionService::MODE_EXISTING_ONLY)
        );

        return response()->json([
            'status' => (string) ($result['status'] ?? 'error'),
            'tags' => array_values((array) ($result['tags'] ?? [])),
            'fallback_used' => (bool) ($result['fallback_used'] ?? true),
            'reason' => $result['reason'] ?? null,
            'last_run' => (array) ($result['last_run'] ?? []),
        ]);
    }
}

