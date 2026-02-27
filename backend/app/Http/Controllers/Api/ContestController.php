<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contest;
use App\Models\Post;
use App\Support\HashtagParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContestController extends Controller
{
    public function active(): JsonResponse
    {
        $contests = Contest::query()
            ->active()
            ->with(['winnerPost:id,user_id,created_at', 'winnerUser:id,username'])
            ->orderBy('starts_at')
            ->get();

        $latestFinished = Contest::query()
            ->where('status', 'finished')
            ->with(['winnerPost:id,user_id,created_at', 'winnerUser:id,username'])
            ->orderByDesc('ends_at')
            ->first();

        return response()->json([
            'data' => $contests,
            'latest_finished' => $latestFinished,
        ]);
    }

    public function participants(Request $request, Contest $contest): JsonResponse
    {
        $validated = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $limit = (int) ($validated['limit'] ?? 100);

        $posts = Post::query()
            ->select(['id', 'user_id', 'content', 'created_at'])
            ->with('user:id,username')
            ->whereBetween('created_at', [$contest->starts_at, $contest->ends_at])
            ->whereNull('parent_id')
            ->publiclyVisible()
            ->notExpired()
            ->orderByDesc('created_at')
            ->limit(500)
            ->get()
            ->filter(function (Post $post) use ($contest) {
                $hashtags = HashtagParser::extract((string) $post->content);

                return $hashtags->contains(strtolower((string) $contest->hashtag));
            })
            ->take($limit)
            ->values()
            ->map(fn (Post $post) => [
                'post_id' => $post->id,
                'user_id' => $post->user_id,
                'username' => $post->user?->username,
                'created_at' => optional($post->created_at)?->toIso8601String(),
            ]);

        return response()->json([
            'data' => $posts,
        ]);
    }
}
