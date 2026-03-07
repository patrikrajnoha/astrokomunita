<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Models\BlogPostComment;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;

class BlogPostCommentController extends Controller
{
    public function index(Request $request, string $slug)
    {
        $blogPost = $this->resolvePublished($slug);

        if (!$blogPost) {
            return response()->json([
                'message' => 'Nenaslo sa',
            ], 404);
        }

        $items = BlogPostComment::query()
            ->where('blog_post_id', $blogPost->id)
            ->with(['user:id,name,email,is_admin'])
            ->orderBy('created_at')
            ->paginate(20);

        if ($request->boolean('withDepth')) {
            $this->appendDepth($items, (int) $blogPost->id);
        }

        return response()->json($items);
    }

    public function store(Request $request, string $slug)
    {
        $blogPost = $this->resolvePublished($slug);

        if (!$blogPost) {
            return response()->json([
                'message' => 'Nenaslo sa',
            ], 404);
        }

        $validated = $request->validate([
            'content' => ['required', 'string', 'min:2', 'max:1000'],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('blog_post_comments', 'id')->where(
                    fn ($query) => $query->where('blog_post_id', $blogPost->id)
                ),
            ],
        ]);

        $comment = BlogPostComment::create([
            'blog_post_id' => $blogPost->id,
            'user_id' => $request->user()->id,
            'parent_id' => $validated['parent_id'] ?? null,
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
                'message' => 'Nenaslo sa',
            ], 404);
        }

        $user = $request->user();
        if ($comment->user_id !== $user->id && !$user->is_admin) {
            return response()->json([
                'message' => 'Zakazane',
            ], 403);
        }

        $comment->delete();

        return response()->json([
            'message' => 'Vymazane',
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

    private function appendDepth(LengthAwarePaginator $items, int $blogPostId): void
    {
        $parentById = BlogPostComment::query()
            ->where('blog_post_id', $blogPostId)
            ->pluck('parent_id', 'id')
            ->map(
                fn ($parentId) => $parentId === null ? null : (int) $parentId
            )
            ->all();

        $depthCache = [];
        $visiting = [];

        $resolveDepth = function (int $commentId) use (&$resolveDepth, &$depthCache, &$visiting, $parentById): int {
            if (array_key_exists($commentId, $depthCache)) {
                return $depthCache[$commentId];
            }

            if (!array_key_exists($commentId, $parentById)) {
                return 0;
            }

            $parentId = $parentById[$commentId];

            if ($parentId === null || $parentId === 0) {
                return $depthCache[$commentId] = 0;
            }

            if (isset($visiting[$commentId])) {
                return $depthCache[$commentId] = 0;
            }

            $visiting[$commentId] = true;
            $depth = $resolveDepth($parentId) + 1;
            unset($visiting[$commentId]);

            return $depthCache[$commentId] = $depth;
        };

        $items->getCollection()->transform(function (BlogPostComment $comment) use ($resolveDepth) {
            $comment->setAttribute('depth', $resolveDepth((int) $comment->id));
            return $comment;
        });
    }
}

