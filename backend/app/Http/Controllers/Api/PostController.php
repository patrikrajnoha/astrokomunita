<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Post\ReplyPostRequest;
use App\Http\Requests\Post\StorePostRequest;
use App\Models\Post;
use App\Services\PostService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;

class PostController extends Controller
{
    public function __construct(
        private readonly PostService $posts,
    ) {
    }

    public function index(Request $request)
    {
        $viewer = $request->user() ?? $request->user('sanctum');

        if ($request->query('scope') === 'me' && !$viewer) {
            return response()->json([
                'message' => 'Neprihlaseny pouzivatel.',
            ], 401);
        }

        $result = $this->posts->getPaginatedFeed([
            'per_page' => $request->query('per_page', 20),
            'kind' => $request->query('kind', 'roots'),
            'with_counts' => $request->query('with') === 'counts',
            'include_hidden' => $request->boolean('include_hidden'),
            'scope' => $request->query('scope'),
            'source' => $request->query('source'),
            'tag' => $request->query('tag'),
        ], $viewer);

        return response()->json($result);
    }

    public function show(Request $request, Post $post)
    {
        $viewer = $request->user();
        $isAdmin = $viewer?->isAdmin() ?? false;

        if ($post->is_hidden && !$isAdmin) {
            return response()->json([
                'message' => 'Not found.',
            ], 404);
        }

        try {
            $payload = $this->posts->getThread($post, $viewer);
        } catch (ModelNotFoundException) {
            return response()->json([
                'message' => 'Not found.',
            ], 404);
        }

        return response()->json($payload);
    }

    public function destroy(Request $request, Post $post)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Neprihlaseny pouzivatel.',
            ], 401);
        }

        $isOwner = (int) $post->user_id === (int) $user->id;
        $isAdmin = (bool) $user->is_admin;

        if (!$isOwner && !$isAdmin) {
            return response()->json([
                'message' => 'Nemate opravnenie zmazat tento post.',
            ], 403);
        }

        $this->posts->deletePost($post);

        return response()->noContent();
    }

    public function store(StorePostRequest $request)
    {
        $post = $this->posts->createPost(
            $request->user(),
            $request->validated('content'),
            $request->file('attachment')
        );

        return response()->json($post, 201);
    }

    public function reply(ReplyPostRequest $request, Post $post)
    {
        if ($post->isFromBot()) {
            return response()->json([
                'message' => 'Replies are disabled on automated news posts.',
                'error' => 'replies_disabled',
            ], 403);
        }

        if ($this->posts->getReplyDepth($post) > 2) {
            return response()->json([
                'message' => 'Max depth je 2 (root -> reply -> reply).',
            ], 422);
        }

        $reply = $this->posts->createReply(
            $request->user(),
            $post,
            $request->validated('content'),
            $request->file('attachment')
        );

        return response()->json($reply, 201);
    }

    public function like(Request $request, Post $post)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Neprihlaseny pouzivatel.',
            ], 401);
        }

        return response()->json($this->posts->likePost($post, $user));
    }

    public function unlike(Request $request, Post $post)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Neprihlaseny pouzivatel.',
            ], 401);
        }

        return response()->json($this->posts->unlikePost($post, $user));
    }
}
