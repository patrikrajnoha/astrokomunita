<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Services\AdminBlogPostService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminBlogPostController extends Controller
{
    public function __construct(
        private readonly AdminBlogPostService $blogPosts,
    ) {
    }

    public function index(Request $request)
    {
        $validated = $request->validate([
            'status' => ['nullable', 'string', Rule::in(['published', 'draft', 'scheduled'])],
        ]);

        return response()->json($this->blogPosts->list($validated['status'] ?? null));
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
        ]);

        $blogPost = $this->blogPosts->create(
            $validated,
            $user->id,
            $request->file('cover_image')
        );

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

        $blogPost = $this->blogPosts->update(
            $blogPost,
            $validated,
            $request->file('cover_image')
        );

        return response()->json($blogPost);
    }

    public function destroy(BlogPost $blogPost)
    {
        $this->blogPosts->delete($blogPost);

        return response()->json([
            'message' => 'Deleted',
        ]);
    }
}
