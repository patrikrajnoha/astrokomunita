<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Services\PostPayloadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProfilePinnedPostController extends Controller
{
    public function __construct(
        private readonly PostPayloadService $payloads,
    ) {
    }

    public function pin(Request $request, Post $post)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Neprihlaseny pouzivatel.',
            ], 401);
        }

        if ((int) $post->user_id !== (int) $user->id) {
            return response()->json([
                'message' => 'Mozes pripnut iba svoj prispevok.',
            ], 403);
        }

        if ($post->parent_id !== null) {
            return response()->json([
                'message' => 'Pripnut sa da iba hlavny prispevok.',
            ], 422);
        }

        DB::transaction(function () use ($user, $post): void {
            Post::query()
                ->where('user_id', $user->id)
                ->whereNotNull('profile_pinned_at')
                ->update(['profile_pinned_at' => null]);

            Post::query()
                ->whereKey($post->id)
                ->update(['profile_pinned_at' => now()]);
        });

        $freshPost = $post->fresh([
            'user:id,name,username,location,bio,is_admin,is_bot,avatar_path,avatar_mode,avatar_color,avatar_icon,avatar_seed',
        ]);

        return response()->json([
            'message' => 'Prispevok bol uspesne pripnuty na profile.',
            'post' => $freshPost ? $this->payloads->serializePost($freshPost, $user) : null,
        ]);
    }

    public function unpin(Request $request, Post $post)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Neprihlaseny pouzivatel.',
            ], 401);
        }

        if ((int) $post->user_id !== (int) $user->id) {
            return response()->json([
                'message' => 'Mozes odopnut iba svoj prispevok.',
            ], 403);
        }

        Post::query()
            ->whereKey($post->id)
            ->update(['profile_pinned_at' => null]);

        $freshPost = $post->fresh([
            'user:id,name,username,location,bio,is_admin,is_bot,avatar_path,avatar_mode,avatar_color,avatar_icon,avatar_seed',
        ]);

        return response()->json([
            'message' => 'Prispevok bol uspesne odopnuty z profilu.',
            'post' => $freshPost ? $this->payloads->serializePost($freshPost, $user) : null,
        ]);
    }
}
