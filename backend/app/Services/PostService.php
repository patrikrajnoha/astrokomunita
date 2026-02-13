<?php

namespace App\Services;

use App\Jobs\ModeratePostJob;
use App\Models\Post;
use App\Models\User;
use App\Support\HashtagParser;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PostService
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly FeedQueryBuilder $feedQueryBuilder,
    ) {
    }

    public function getPaginatedFeed(array $filters, ?User $user): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 20);
        $perPage = max(1, min($perPage, 50));

        $kind = (string) ($filters['kind'] ?? 'roots');
        $withCounts = (bool) ($filters['with_counts'] ?? false);
        $includeHidden = (bool) ($filters['include_hidden'] ?? false);
        $scope = $filters['scope'] ?? null;
        $source = $filters['source'] ?? null;
        $tag = isset($filters['tag']) ? strtolower((string) $filters['tag']) : null;
        $query = $this->feedQueryBuilder->build([
            'kind' => $kind,
            'with_counts' => $withCounts,
            'include_hidden' => $includeHidden,
            'tag' => $tag,
            'order' => 'pinned_then_created',
            'sources_exclude' => ['astrobot', 'nasa_rss'],
        ], $user);

        if ($scope === 'me' && $user) {
            $query->where('user_id', $user->id);
        }

        if ($source) {
            if ($source === 'astrobot') {
                $query->where('source_name', 'astrobot');
            } elseif ($source === 'users') {
                $query->where(function ($usersSourceQuery) {
                    $usersSourceQuery->whereNull('source_name')
                        ->orWhere('source_name', '!=', 'astrobot');
                });
            }
        }

        return $query->paginate($perPage)->withQueryString();
    }

    public function getThread(Post $post, ?User $viewer): array
    {
        $isAdmin = $viewer?->isAdmin() ?? false;
        $root = $this->resolveRootPost($post);

        if (($root->is_hidden || $root->hidden_at || $root->moderation_status === 'blocked') && !$isAdmin) {
            throw new ModelNotFoundException();
        }

        $threadQuery = Post::query()
            ->where(function ($query) use ($root) {
                $query->where('id', $root->id)
                    ->orWhere('root_id', $root->id)
                    ->orWhere('parent_id', $root->id);
            })
            ->with([
                'user:id,name,username,location,bio,is_admin,avatar_path',
                'tags:id,name',
                'hashtags:id,name',
            ])
            ->withCount('likes');

        if (!$isAdmin) {
            $threadQuery->publiclyVisible();
        }

        $threadQuery->notExpired();

        if ($viewer) {
            $threadQuery->withExists([
                'likes as liked_by_me' => fn ($likesQuery) => $likesQuery->where('user_id', $viewer->id),
            ]);
        }

        $thread = $threadQuery->orderBy('created_at')->get();

        $rootPost = $thread->firstWhere('id', $root->id);
        if ($rootPost) {
            $rootPost->setAttribute(
                'replies_count',
                $thread->filter(fn (Post $threadPost) => (int) $threadPost->id !== (int) $root->id)->count()
            );
        }

        $byParent = $thread->groupBy('parent_id');
        $nestedReplies = $byParent->get($root->id, collect())
            ->map(function (Post $reply) use ($byParent) {
                $reply->setRelation('replies', $byParent->get($reply->id, collect())->values());
                return $reply;
            })
            ->values();

        return [
            'post' => $post,
            'root' => $rootPost,
            'thread' => $thread,
            'replies' => $nestedReplies,
        ];
    }

    public function createPost(User $user, string $content, ?UploadedFile $attachment = null): Post
    {
        return DB::transaction(function () use ($user, $content, $attachment) {
            $moderationEnabled = (bool) config('moderation.enabled', true);
            $post = new Post();
            $post->user_id = $user->id;
            $post->content = $content;
            $post->parent_id = null;
            $post->root_id = null;
            $post->depth = 0;
            $post->is_hidden = false;
            $post->moderation_status = $moderationEnabled ? 'pending' : 'ok';
            $post->moderation_summary = null;
            $post->hidden_reason = null;
            $post->hidden_at = null;

            if ($attachment) {
                $path = $attachment->store('posts', 'public');
                $post->attachment_path = $path;
                $post->attachment_mime = $attachment->getClientMimeType();
                $post->attachment_original_name = $attachment->getClientOriginalName();
                $post->attachment_size = $attachment->getSize();
                $isImageAttachment = str_starts_with((string) $post->attachment_mime, 'image/');
                $post->attachment_moderation_status = ($moderationEnabled && $isImageAttachment)
                    ? 'pending'
                    : null;
                $post->attachment_moderation_summary = null;
                $post->attachment_is_blurred = $moderationEnabled && $isImageAttachment;
                $post->attachment_hidden_at = null;
            }

            $post->save();

            HashtagParser::syncHashtags($post, $content);
            if ($moderationEnabled) {
                $this->logModerationQueueDiagnostics();
                ModeratePostJob::dispatch((int) $post->id)->afterCommit();
            }

            return $post->load([
                'user:id,name,username,location,bio,is_admin,avatar_path',
                'tags:id,name',
                'hashtags:id,name',
            ]);
        });
    }

    public function createReply(User $user, Post $parent, string $content, ?UploadedFile $attachment = null): Post
    {
        return DB::transaction(function () use ($user, $parent, $content, $attachment) {
            $moderationEnabled = (bool) config('moderation.enabled', true);
            $parentDepth = $parent->depth;
            if ($parentDepth === null) {
                $parentDepth = $parent->parent_id ? 1 : 0;
            }
            $depth = ((int) $parentDepth) + 1;
            $rootId = $parent->root_id ?: ($parent->parent_id ?: $parent->id);

            $reply = new Post();
            $reply->user_id = $user->id;
            $reply->content = $content;
            $reply->parent_id = $parent->id;
            $reply->root_id = $rootId;
            $reply->depth = $depth;
            $reply->moderation_status = $moderationEnabled ? 'pending' : 'ok';
            $reply->moderation_summary = null;
            $reply->hidden_reason = null;
            $reply->hidden_at = null;

            if ($attachment) {
                $path = $attachment->store('posts', 'public');
                $reply->attachment_path = $path;
                $reply->attachment_mime = $attachment->getClientMimeType();
                $reply->attachment_original_name = $attachment->getClientOriginalName();
                $reply->attachment_size = $attachment->getSize();
                $isImageAttachment = str_starts_with((string) $reply->attachment_mime, 'image/');
                $reply->attachment_moderation_status = ($moderationEnabled && $isImageAttachment)
                    ? 'pending'
                    : null;
                $reply->attachment_moderation_summary = null;
                $reply->attachment_is_blurred = $moderationEnabled && $isImageAttachment;
                $reply->attachment_hidden_at = null;
            }

            $reply->save();

            HashtagParser::syncHashtags($reply, $content);
            if ($moderationEnabled) {
                $this->logModerationQueueDiagnostics();
                ModeratePostJob::dispatch((int) $reply->id)->afterCommit();
            }

            return $reply->load([
                'user:id,name,username,location,bio,is_admin,avatar_path',
                'tags:id,name',
                'hashtags:id,name',
            ]);
        });
    }

    public function getReplyDepth(Post $parent): int
    {
        $parentDepth = $parent->depth;
        if ($parentDepth === null) {
            $parentDepth = $parent->parent_id ? 1 : 0;
        }
        return ((int) $parentDepth) + 1;
    }

    public function deletePost(Post $post): void
    {
        if ($post->attachment_path) {
            Storage::disk('public')->delete($post->attachment_path);
        }

        $post->delete();
    }

    public function likePost(Post $post, User $user): array
    {
        DB::table('post_likes')->updateOrInsert(
            ['user_id' => $user->id, 'post_id' => $post->id],
            ['created_at' => now()]
        );

        $post->loadCount('likes');
        $this->notifications->createPostLiked($post->user_id, $user->id, $post->id);

        return [
            'likes_count' => $post->likes_count,
            'liked_by_me' => true,
        ];
    }

    public function unlikePost(Post $post, User $user): array
    {
        DB::table('post_likes')
            ->where('user_id', $user->id)
            ->where('post_id', $post->id)
            ->delete();

        $post->loadCount('likes');

        return [
            'likes_count' => $post->likes_count,
            'liked_by_me' => false,
        ];
    }

    private function resolveRootPost(Post $post): Post
    {
        if ($post->root_id) {
            return Post::query()->findOrFail($post->root_id);
        }

        if ($post->parent_id) {
            $parent = Post::query()
                ->select(['id', 'parent_id', 'root_id'])
                ->findOrFail($post->parent_id);

            if ($parent->root_id) {
                return Post::query()->findOrFail($parent->root_id);
            }

            if ($parent->parent_id) {
                return Post::query()->findOrFail($parent->parent_id);
            }

            return $parent;
        }

        return $post;
    }

    private function logModerationQueueDiagnostics(): void
    {
        if (!app()->environment('local')) {
            return;
        }

        static $warningEmitted = false;

        if ($warningEmitted) {
            return;
        }

        $queueConnection = (string) config('queue.default', 'sync');
        if ($queueConnection === 'sync') {
            return;
        }

        $warningEmitted = true;

        Log::warning('Moderation jobs are queued asynchronously in local env. Ensure a worker is running.', [
            'queue_connection' => $queueConnection,
            'hint' => 'Run: php artisan queue:work',
        ]);
    }
}
