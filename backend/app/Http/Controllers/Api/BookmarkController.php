<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\User;
use App\Services\PollService;
use App\Services\PostPayloadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BookmarkController extends Controller
{
    public function __construct(
        private readonly PollService $polls,
        private readonly PostPayloadService $payloads,
    ) {
    }

    public function store(Request $request, Post $post)
    {
        $user = $request->user();

        DB::table('post_user_bookmarks')->insertOrIgnore([
            'user_id' => $user->id,
            'post_id' => $post->id,
            'created_at' => now(),
        ]);

        return response()->json([
            'is_bookmarked' => true,
        ]);
    }

    public function destroy(Request $request, Post $post)
    {
        $user = $request->user();

        DB::table('post_user_bookmarks')
            ->where('user_id', $user->id)
            ->where('post_id', $post->id)
            ->delete();

        return response()->json([
            'is_bookmarked' => false,
        ]);
    }

    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 20);
        $perPage = max(1, min($perPage, 50));

        $viewer = $this->resolveViewer($request);

        $query = Post::query()
            ->select(['posts.*', 'post_user_bookmarks.created_at as bookmarked_at'])
            ->join('post_user_bookmarks', function ($join) use ($viewer) {
                $join->on('post_user_bookmarks.post_id', '=', 'posts.id')
                    ->where('post_user_bookmarks.user_id', '=', $viewer->id);
            })
            ->with(array_merge([
                'user:id,name,username,location,bio,is_admin,avatar_path,avatar_mode,avatar_color,avatar_icon,avatar_seed',
                'replies.user:id,name,username,location,bio,is_admin,avatar_path,avatar_mode,avatar_color,avatar_icon,avatar_seed',
                'parent.user:id,name,username,location,bio,is_admin,avatar_path,avatar_mode,avatar_color,avatar_icon,avatar_seed',
                'tags:id,name',
                'hashtags:id,name',
            ], $this->polls->pollRelations($viewer->id)))
            ->withCount('likes')
            ->withExists([
                'likes as liked_by_me' => fn ($likesQuery) => $likesQuery->where('user_id', $viewer->id),
                'bookmarkedBy as is_bookmarked' => fn ($bookmarkQuery) => $bookmarkQuery->where('user_id', $viewer->id),
            ])
            ->publiclyVisible()
            ->notExpired()
            ->orderByDesc('post_user_bookmarks.created_at')
            ->orderByDesc('posts.id');

        $paginator = $query->paginate($perPage)->withQueryString();

        return response()->json(
            $this->payloads->serializePaginator($paginator, $viewer)
        );
    }

    private function resolveViewer(Request $request): User
    {
        return $request->user() ?? $request->user('sanctum');
    }
}
