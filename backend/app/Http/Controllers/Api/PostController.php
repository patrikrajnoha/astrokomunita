<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Services\HashtagParser;
use App\Services\NotificationService;
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
     * GET /api/posts?source=astrobot|users
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
        $isAdmin = $user?->isAdmin() ?? false;

        $query = Post::query()
            ->with([
                'user:id,name,username,email,location,bio,is_admin,avatar_path',
                'replies.user:id,name,username,email,location,bio,is_admin,avatar_path',
                'parent.user:id,name,username,email,location,bio,is_admin,avatar_path',
                'tags:id,name',
                'hashtags:id,name',
            ])
            ->orderByRaw('pinned_at IS NULL DESC, pinned_at DESC, created_at DESC');

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
                'parent.user:id,name,username,email,location,bio,is_admin,avatar_path',
            ]);
        } elseif ($kind === 'media') {
            $query->whereNotNull('attachment_path');
            $query->with([
                'parent.user:id,name,username,email,location,bio,is_admin,avatar_path',
            ]);
        } else {
            $query->whereNull('parent_id');
        }

        if (!$request->boolean('include_hidden') || !$isAdmin) {
            $query->where('is_hidden', false);
        }

        // Exclude expired AstroBot posts from public feed
        $query->notExpired();

        // Exclude AstroBot posts from main feed - they have separate feed
        $query->where(function ($q) {
            $q->whereNull('source_name')
              ->orWhereNotIn('source_name', ['astrobot', 'nasa_rss']);
        });

        if ($request->query('scope') === 'me') {
            if (!$user) {
                return response()->json([
                    'message' => 'Neprihlaseny pouzivatel.',
                ], 401);
            }

            $query->where('user_id', $user->id);
            
            // Also exclude AstroBot posts from user's own feed
            $query->where(function ($q) {
                $q->whereNull('source_name')
                  ->orWhereNotIn('source_name', ['astrobot', 'nasa_rss']);
            });
        }

        // Filter by source (e.g., astrobot, users) - kept for backward compatibility
        if ($source = $request->query('source')) {
            if ($source === 'astrobot') {
                $query->where('source_name', 'astrobot');
            } elseif ($source === 'users') {
                $query->whereNull('source_name')->orWhere('source_name', '!=', 'astrobot');
            }
        }

        // Filter by tag - try both name and slug for robustness
        if ($tag = $request->query('tag')) {
            $tag = strtolower($tag);
            $query->whereHas('tags', function ($q) use ($tag) {
                $q->where('name', $tag)->orWhere('slug', $tag);
            });
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
        $isAdmin = $viewer?->isAdmin() ?? false;

        if ($post->is_hidden && !$isAdmin) {
            return response()->json([
                'message' => 'Not found.',
            ], 404);
        }

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

        if ($root->is_hidden && !$isAdmin) {
            return response()->json([
                'message' => 'Not found.',
            ], 404);
        }

        // Načítanie celého vlákna naraz - efektívnejšie
        $threadQuery = Post::query()
            ->where(function ($q) use ($root) {
                $q->where('id', $root->id)
                  ->orWhere('root_id', $root->id)
                  ->orWhere('parent_id', $root->id); // fallback pre stare data
            })
            ->with([
                'user:id,name,username,email,location,bio,is_admin,avatar_path',
                'tags:id,name',
                'hashtags:id,name',
            ])
            ->withCount('likes');
            
        if (!$isAdmin) {
            $threadQuery->where('is_hidden', false);
        }

        // Exclude expired AstroBot posts from thread view
        $threadQuery->notExpired();

        if ($viewer) {
            $threadQuery->withExists([
                'likes as liked_by_me' => fn ($q) => $q->where('user_id', $viewer->id),
            ]);
        }

        $thread = $threadQuery->orderBy('created_at')->get();

        // Najdenie root postu v thread a pridanie counts
        $rootPost = $thread->firstWhere('id', $root->id);
        if ($rootPost) {
            $rootPost->setAttribute(
                'replies_count',
                $thread->filter(fn ($p) => (int) $p->id !== (int) $root->id)->count()
            );
        }

        // Vytvorenie štruktúry replies
        $byParent = $thread->groupBy('parent_id');
        $nestedReplies = $byParent->get($root->id, collect())
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
            'root' => $rootPost,
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

        // Parse and sync hashtags
        HashtagParser::syncHashtags($post, $validated['content']);

        $post->load([
            'user:id,name,username,email,location,bio,is_admin,avatar_path',
            'tags:id,name',
            'hashtags:id,name',
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

        // Parse and sync hashtags for reply
        HashtagParser::syncHashtags($reply, $validated['content']);

        $reply->load([
            'user:id,name,username,email,location,bio,is_admin,avatar_path',
            'tags:id,name',
            'hashtags:id,name',
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
        app(NotificationService::class)->createPostLiked(
            $post->user_id,
            $user->id,
            $post->id
        );

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
