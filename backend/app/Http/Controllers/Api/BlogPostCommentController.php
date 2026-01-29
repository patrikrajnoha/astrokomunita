<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Models\BlogPostComment;
use Illuminate\Http\Request;

class BlogPostCommentController extends Controller
{
    public function index(string $slug)
    {
        $blogPost = $this->resolvePublished($slug);

        if (!$blogPost) {
            return response()->json([
                'message' => 'Not found',
            ], 404);
        }

        $items = BlogPostComment::query()
            ->where('blog_post_id', $blogPost->id)
            ->with(['user:id,name,email,is_admin'])
            ->orderBy('created_at')
            ->paginate(20);

        return response()->json($items);
    }

    public function store(Request $request, string $slug)
    {
        $blogPost = $this->resolvePublished($slug);

        if (!$blogPost) {
            return response()->json([
                'message' => 'Not found',
            ], 404);
        }

        $validated = $request->validate([
            'content' => ['required', 'string', 'min:2', 'max:1000'],
        ]);

        $comment = BlogPostComment::create([
            'blog_post_id' => $blogPost->id,
            'user_id' => $request->user()->id,
            'content' => $validated['content'],
        ]);

        $comment->load(['user:id,name,email,is_admin']);

        return response()->json($comment, 201);
    }

    public function destroy(Request $request, string $slug, BlogPostComment $comment)
    {
        $blogPost = $this->resolvePublished($slug);

        if (!$blogPost || $comment->blog_post_id !== $blogPost->id) {
            return response()->json([
                'message' => 'Not found',
            ], 404);
        }

        $user = $request->user();
        if ($comment->user_id !== $user->id && !$user->is_admin) {
            return response()->json([
                'message' => 'Forbidden',
            ], 403);
        }

        $comment->delete();

        return response()->json([
            'message' => 'Deleted',
        ]);
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
