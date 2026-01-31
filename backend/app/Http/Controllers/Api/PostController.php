<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class PostController extends Controller
{
    /**
     * GET /api/posts
     * GET /api/posts?scope=me
     * GET /api/posts?kind=roots|replies|media
     * GET /api/posts?per_page=...
     *
     * Public community feed + optional filters.
     * MVP replies:
     * - default returns only root posts (parent_id NULL)
     * - replies_count added for root posts
     */
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 20);
        if ($perPage < 1) {
            $perPage = 1;
        }
        if ($perPage > 50) {
            $perPage = 50;
        }

        $kind = $request->query('kind', 'roots');
        $withCounts = $request->query('with') === 'counts';

        $user = $request->user();

        $query = Post::query()
            ->with([
                'user:id,name,username,email,location,bio,is_admin',
            ])
            ->latest();

        $counts = ['likes'];
        if ($withCounts) {
            $counts[] = 'replies';
        }
        $query->withCount($counts);

        if ($user) {
            $query->withExists([
                'likes as liked_by_me' => fn ($q) => $q->where('user_id', $user->id),
            ]);
        }

        if ($kind === 'replies') {
            $query->whereNotNull('parent_id');
            $query->with([
                'parent.user:id,name,username,email,location,bio,is_admin',
            ]);
        } elseif ($kind === 'media') {
            $query->whereNotNull('attachment_path');
            $query->with([
                'parent.user:id,name,username,email,location,bio,is_admin',
            ]);
        } else {
            $query->whereNull('parent_id');
        }

        if ($request->query('scope') === 'me') {
            if (!$user) {
                return response()->json([
                    'message' => 'Neprihlaseny pouzivatel.',
                ], 401);
            }

            $query->where('user_id', $user->id);
        }

        return response()->json(
            $query->paginate($perPage)->withQueryString()
        );
    }

    /**
     * GET /api/posts/{post}
     *
     * Detail root post + jeho replies
     * Verejne dostupny
     */
    public function show(Request $request, Post $post)
    {
        $viewer = $request->user();

        // Ak niekto otvori reply URL priamo, vratime root post + cele vlakno
        $root = $post;
        if ($post->root_id) {
            $root = Post::query()->findOrFail($post->root_id);
        } elseif ($post->parent_id) {
            $parent = Post::query()
                ->select(['id', 'parent_id', 'root_id'])
                ->findOrFail($post->parent_id);

            if ($parent->root_id) {
                $root = Post::query()->findOrFail($parent->root_id);
            } elseif ($parent->parent_id) {
                $root = Post::query()->findOrFail($parent->parent_id);
            } else {
                $root = $parent;
            }
        }

        $root->load([
            'user:id,name,username,email,location,bio,is_admin',
        ]);
        $root->loadCount('likes');
        if ($viewer) {
            $root->setAttribute(
                'liked_by_me',
                $root->likes()->where('user_id', $viewer->id)->exists()
            );
        }

        $threadQuery = Post::query()
            ->where('id', $root->id)
            ->orWhere('root_id', $root->id)
            ->orWhere('parent_id', $root->id) // fallback pre stare data bez root_id
            ->with([
                'user:id,name,username,email,location,bio,is_admin',
            ])
            ->withCount('likes');

        if ($viewer) {
            $threadQuery->withExists([
                'likes as liked_by_me' => fn ($q) => $q->where('user_id', $viewer->id),
            ]);
        }

        $thread = $threadQuery->orderBy('created_at')->get();

        $rootId = $root->id;
        $root->setAttribute(
            'replies_count',
            $thread->filter(fn ($p) => (int) $p->id !== (int) $rootId)->count()
        );

        $post->load([
            'user:id,name,username,email,location,bio,is_admin',
        ]);
        $post->loadCount('likes');
        if ($viewer) {
            $post->setAttribute(
                'liked_by_me',
                $post->likes()->where('user_id', $viewer->id)->exists()
            );
        }

        // Optional nested replies (max depth 2): root.replies + reply.replies
        $byParent = $thread->groupBy('parent_id');
        $nestedReplies = $byParent->get($rootId, collect())
            ->map(function (Post $reply) use ($byParent) {
                $reply->setRelation(
                    'replies',
                    $byParent->get($reply->id, collect())->values()
                );
                return $reply;
            })
            ->values();

        return response()->json([
            'post' => $post,
            'root' => $root,
            'thread' => $thread,
            'replies' => $nestedReplies,
        ]);
    }

    
    /**
     * DELETE /api/posts/{post}
     *
     * Delete post (author or admin only)
     */
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

        if ($post->attachment_path) {
            Storage::disk('public')->delete($post->attachment_path);
        }

        $post->delete();

        return response()->noContent();
    }

/**
     * POST /api/posts
     *
     * Vytvorenie postu (vyzaduje auth)
     * Podporuje text + 1 prilohu
     * + volitelne parent_id (reply na root)
     */
    public function store(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'content' => [
                'required',
                'string',
                'min:1',
                'max:280',
            ],

            // 5 MB limit, bezpecne typy (MVP)
            'attachment' => [
                'nullable',
                'file',
                'max:5120',
                'mimes:jpg,jpeg,png,webp,gif,pdf,txt,doc,docx',
            ],
        ]);

        $data = [
            'user_id' => $user->id,
            'content' => $validated['content'],
            'parent_id' => null,
            'root_id' => null,
            'depth' => 0,
        ];

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');

            // ulozenie do storage/app/public/posts/...
            $path = $file->store('posts', 'public');

            $data['attachment_path'] = $path;
            $data['attachment_mime'] = $file->getClientMimeType();
            $data['attachment_original_name'] = $file->getClientOriginalName();
            $data['attachment_size'] = $file->getSize();
        }

        $post = Post::create($data);

        $post->load([
            'user:id,name,username,email,location,bio,is_admin',
        ]);

        return response()->json($post, 201);
    }

    /**
     * POST /api/posts/{post}/reply
     *
     * Reply na post (auth required)
     */
    public function reply(Request $request, Post $post)
    {
        $user = $request->user();

        $validated = $request->validate([
            'content' => [
                'required',
                'string',
                'min:1',
                'max:280',
            ],

            // 5 MB limit, bezpecne typy (MVP)
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

            // ulozenie do storage/app/public/posts/...
            $path = $file->store('posts', 'public');

            $data['attachment_path'] = $path;
            $data['attachment_mime'] = $file->getClientMimeType();
            $data['attachment_original_name'] = $file->getClientOriginalName();
            $data['attachment_size'] = $file->getSize();
        }

        $reply = Post::create($data);

        $reply->load([
            'user:id,name,username,email,location,bio,is_admin',
        ]);

        return response()->json($reply, 201);
    }

    /**
     * POST /api/posts/{post}/like
     *
     * Like post (auth required)
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
     *
     * Unlike post (auth required)
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
