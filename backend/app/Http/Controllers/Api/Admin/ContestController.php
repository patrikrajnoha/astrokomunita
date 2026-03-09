<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Hashtag;
use App\Models\Post;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ContestController extends Controller
{
    public function hashtagsPreview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => ['nullable', 'string', 'max:64'],
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'hashtags_limit' => ['nullable', 'integer', 'min:1', 'max:30'],
            'posts_limit' => ['nullable', 'integer', 'min:1', 'max:30'],
        ]);

        $from = Carbon::parse($validated['from'])->startOfDay();
        $to = Carbon::parse($validated['to'])->endOfDay();
        $normalizedQuery = strtolower(ltrim(trim((string) ($validated['query'] ?? '')), '#'));
        $hashtagsLimit = (int) ($validated['hashtags_limit'] ?? 12);
        $postsLimit = (int) ($validated['posts_limit'] ?? 5);

        $hashtags = Hashtag::query()
            ->select(['id', 'name'])
            ->when($normalizedQuery !== '', fn (Builder $builder) => $builder->where('name', 'like', '%' . $normalizedQuery . '%'))
            ->whereHas('posts', fn (Builder $builder) => $this->applyPreviewPostFilters($builder, $from, $to))
            ->withCount([
                'posts as posts_count' => fn (Builder $builder) => $this->applyPreviewPostFilters($builder, $from, $to),
            ])
            ->orderByDesc('posts_count')
            ->orderBy('name')
            ->limit($hashtagsLimit)
            ->get();

        $data = $hashtags->map(function (Hashtag $hashtag) use ($from, $to, $postsLimit): array {
            $postsQuery = $hashtag->posts()
                ->select([
                    'posts.id',
                    'posts.user_id',
                    'posts.content',
                    'posts.created_at',
                    'posts.attachment_path',
                    'posts.attachment_web_path',
                    'posts.attachment_original_path',
                    'posts.attachment_mime',
                    'posts.attachment_original_mime',
                    'posts.attachment_original_name',
                    'posts.attachment_web_width',
                    'posts.attachment_web_height',
                ])
                ->with(['user:id,username,name,email'])
                ->orderByDesc('posts.created_at')
                ->limit($postsLimit);

            $this->applyPreviewPostFilters($postsQuery, $from, $to);

            $posts = $postsQuery->get()
                ->map(static fn (Post $post): array => [
                    'id' => (int) $post->id,
                    'content' => (string) $post->content,
                    'created_at' => optional($post->created_at)?->toIso8601String(),
                    'media' => [
                        'attachment_url' => $post->attachment_url,
                        'attachment_download_url' => $post->attachment_download_url,
                        'mime' => (string) ($post->attachment_original_mime ?: $post->attachment_mime ?: ''),
                        'width' => $post->attachment_width,
                        'height' => $post->attachment_height,
                        'is_image' => str_starts_with(
                            strtolower((string) ($post->attachment_original_mime ?: $post->attachment_mime ?: '')),
                            'image/'
                        ),
                    ],
                    'user' => $post->user ? [
                        'id' => (int) $post->user->id,
                        'username' => (string) ($post->user->username ?? ''),
                        'name' => (string) ($post->user->name ?? ''),
                        'email' => $post->user->email,
                    ] : null,
                ])
                ->values();

            return [
                'id' => (int) $hashtag->id,
                'name' => (string) $hashtag->name,
                'posts_count' => (int) $hashtag->posts_count,
                'posts' => $posts,
            ];
        })->values();

        return response()->json([
            'data' => $data,
        ]);
    }

    private function applyPreviewPostFilters($query, Carbon $from, Carbon $to): void
    {
        $query
            ->whereBetween('posts.created_at', [$from, $to])
            ->whereNull('posts.parent_id')
            ->publiclyVisible()
            ->notExpired();
    }
}
