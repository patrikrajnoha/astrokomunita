<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePostRequest;
use App\Http\Requests\UpdatePostRequest;
use App\Http\Resources\PostResource;
use App\Models\Post;
use App\Services\PostService;
use App\Services\HashtagParser;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class PostControllerRefactored extends Controller
{
    public function __construct(
        private PostService $postService
    ) {}

    /**
     * GET /api/posts
     * Public community feed with optional filters
     */
    public function index(Request $request): JsonResponse
    {
        $filters = [
            'kind' => $request->query('kind', 'roots'),
            'with_counts' => $request->query('with') === 'counts',
            'user' => $request->user(),
            'include_hidden' => $request->boolean('include_hidden'),
        ];

        // Scope filter
        if ($request->query('scope') === 'me') {
            if (!$request->user()) {
                return response()->json([
                    'message' => 'Neprihlaseny pouzivatel.',
                ], 401);
            }
            $filters['user_id'] = $request->user()->id;
        }

        // Source filter
        if ($source = $request->query('source')) {
            $filters['source'] = $source;
        }

        $perPage = (int) $request->query('per_page', 20);
        $posts = $this->postService->getPostsPaginated($filters, $perPage);

        return response()->json(
            PostResource::collection($posts)->additional([
                'next_page_url' => $posts->nextPageUrl(),
            ])
        );
    }

    /**
     * POST /api/posts
     * Create a new post
     */
    public function store(StorePostRequest $request)
    {
        $data = $request->validated();
        $data['attachment'] = $request->file('attachment');

        $post = $this->postService->createPost($data, $request->user());

        return new PostResource($post);
    }

    /**
     * GET /api/posts/{id}
     * Get a specific post
     */
    public function show(Post $post)
    {
        $post->load(['user', 'tags', 'replies.user', 'parent.user']);
        
        return new PostResource($post);
    }

    /**
     * PUT/PATCH /api/posts/{id}
     * Update a post
     */
    public function update(UpdatePostRequest $request, Post $post)
    {
        $this->authorize('update', $post);
        
        $post = $this->postService->updatePost($post, $request->validated());
        
        return new PostResource($post);
    }

    /**
     * DELETE /api/posts/{id}
     * Delete a post
     */
    public function destroy(Post $post): JsonResponse
    {
        $this->authorize('delete', $post);
        
        $this->postService->deletePost($post);
        
        return response()->json(null, 204);
    }

    /**
     * POST /api/posts/{post}/reply
     * Reply to a post
     */
    public function reply(Request $request, Post $post)
    {
        $user = $request->user();

        // Prevent replies on AstroBot posts - they are broadcast-only
        if ($post->isFromBot()) {
            return response()->json([
                'message' => 'Replies are disabled on automated news posts.',
                'error' => 'replies_disabled'
            ], 403);
        }

        $validated = $request->validate([
            'content' => [
                'required',
                'string',
                'min:1',
                'max:280',
            ],
            'attachment' => [
                'nullable',
                'file',
                'max:5120',
                'mimes:jpg,jpeg,png,webp,gif,pdf,txt,doc,docx',
            ],
        ]);

        $parentDepth = $post->depth;
        if ($parentDepth === null) {
            $parentDepth = $post->parent_id ? 1 : 0;
        }
        $parentDepth = (int) $parentDepth;
        $depth = $parentDepth + 1;
        if ($depth > 2) {
            return response()->json([
                'message' => 'Max depth je 2 (root -> reply -> reply).',
            ], 422);
        }

        $rootId = $post->root_id ?: ($post->parent_id ?: $post->id);

        $data = [
            'user_id' => $user->id,
            'content' => $validated['content'],
            'parent_id' => $post->id,
            'root_id' => $rootId,
            'depth' => $depth,
        ];

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $path = $file->store('posts', 'public');
            $data['attachment_path'] = $path;
            $data['attachment_mime'] = $file->getClientMimeType();
            $data['attachment_original_name'] = $file->getClientOriginalName();
            $data['attachment_size'] = $file->getSize();
        }

        $reply = Post::create($data);

        // Parse and sync hashtags for reply
        HashtagParser::syncTags($reply, $validated['content']);

        $reply->load([
            'user:id,name,username,email,location,bio,is_admin,avatar_path',
            'tags:id,name',
        ]);

        return response()->json($reply, 201);
    }

    /**
     * POST /api/posts/{post}/like
     * Like a post
     */
    public function like(Request $request, Post $post)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Neprihlaseny pouzivatel.',
            ], 401);
        }

        DB::table('post_likes')->updateOrInsert(
            ['user_id' => $user->id, 'post_id' => $post->id],
            ['created_at' => now()]
        );

        $post->loadCount('likes');

        return response()->json([
            'likes_count' => $post->likes_count,
            'liked_by_me' => true,
        ]);
    }

    /**
     * DELETE /api/posts/{post}/like
     * Unlike a post
     */
    public function unlike(Request $request, Post $post)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Neprihlaseny pouzivatel.',
            ], 401);
        }

        DB::table('post_likes')
            ->where('user_id', $user->id)
            ->where('post_id', $post->id)
            ->delete();

        $post->loadCount('likes');

        return response()->json([
            'likes_count' => $post->likes_count,
            'liked_by_me' => false,
        ]);
    }
}
