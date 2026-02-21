<?php

namespace App\Services;

use App\Jobs\ModeratePostJob;
use App\Models\Post;
use App\Models\User;
use App\Services\Storage\ImageVariantService;
use App\Services\Storage\MediaStorageService;
use App\Support\HashtagParser;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PostService
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly FeedQueryBuilder $feedQueryBuilder,
        private readonly MediaStorageService $mediaStorage,
        private readonly ImageVariantService $imageVariants,
        private readonly PollService $polls,
        private readonly UserActivityService $userActivity,
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
            ->with(array_merge([
                'user:id,name,username,location,bio,is_admin,avatar_path',
                'tags:id,name',
                'hashtags:id,name',
            ], $this->polls->pollRelations($viewer?->id)))
            ->withCount('likes');

        if (!$isAdmin) {
            $threadQuery->publiclyVisible();
        }

        $threadQuery->notExpired();

        if ($viewer) {
            $threadQuery->withExists([
                'likes as liked_by_me' => fn ($likesQuery) => $likesQuery->where('user_id', $viewer->id),
                'bookmarkedBy as is_bookmarked' => fn ($bookmarksQuery) => $bookmarksQuery->where('user_id', $viewer->id),
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

        $currentPost = $thread->firstWhere('id', $post->id) ?? $post;

        return [
            'post' => $currentPost,
            'root' => $rootPost,
            'thread' => $thread,
            'replies' => $nestedReplies,
        ];
    }

    public function createPost(User $user, string $content, ?UploadedFile $attachment = null, ?array $pollInput = null): Post
    {
        if (is_array($pollInput) && $attachment) {
            throw ValidationException::withMessages([
                'attachment' => 'Poll a prilohy sa nedaju kombinovat.',
            ]);
        }

        return DB::transaction(function () use ($user, $content, $attachment, $pollInput) {
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

            $post->save();

            if (is_array($pollInput)) {
                $this->polls->createForPost($post, $pollInput);
            }

            if ($attachment) {
                $this->attachFileToPost(
                    post: $post,
                    attachment: $attachment,
                    user: $user,
                    moderationEnabled: $moderationEnabled
                );
            }

            HashtagParser::syncHashtags($post, $content);
            if ($moderationEnabled) {
                $this->logModerationQueueDiagnostics();
                ModeratePostJob::dispatch((int) $post->id)->afterCommit();
            }

            DB::afterCommit(fn () => $this->userActivity->forgetActivity($user));

            return $post->load([
                'user:id,name,username,location,bio,is_admin,avatar_path',
                'tags:id,name',
                'hashtags:id,name',
                ...$this->polls->pollRelations($user->id),
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

            $reply->save();

            if ($attachment) {
                $this->attachFileToPost(
                    post: $reply,
                    attachment: $attachment,
                    user: $user,
                    moderationEnabled: $moderationEnabled
                );
            }

            HashtagParser::syncHashtags($reply, $content);
            if ($moderationEnabled) {
                $this->logModerationQueueDiagnostics();
                ModeratePostJob::dispatch((int) $reply->id)->afterCommit();
            }

            DB::afterCommit(fn () => $this->userActivity->forgetActivity($user));

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
        $ownerId = (int) $post->user_id;

        $this->mediaStorage->delete($post->attachment_path);
        $this->mediaStorage->delete($post->attachment_web_path);
        $this->mediaStorage->delete($post->attachment_original_path, $this->mediaStorage->privateDiskName());

        $post->delete();
        $this->userActivity->forgetActivity($ownerId);
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

    private function attachFileToPost(Post $post, UploadedFile $attachment, User $user, bool $moderationEnabled): void
    {
        $mime = strtolower(trim((string) ($attachment->getMimeType() ?: $attachment->getClientMimeType())));
        $isImageAttachment = str_starts_with($mime, 'image/');

        if ($isImageAttachment && !$this->imageVariants->isAllowedImageMime($mime)) {
            throw ValidationException::withMessages([
                'attachment' => 'Unsupported image format.',
            ]);
        }

        $post->attachment_original_name = $attachment->getClientOriginalName();
        $post->attachment_moderation_summary = null;
        $post->attachment_hidden_at = null;

        if ($isImageAttachment) {
            $variants = $this->imageVariants->storePostImageVariants(
                uploadedFile: $attachment,
                postId: (int) $post->id,
                mediaId: (int) $post->id,
                userId: (int) $user->id
            );

            $post->attachment_path = $variants['web_path'];
            $post->attachment_web_path = $variants['web_path'];
            $post->attachment_original_path = $variants['original_path'];
            $post->attachment_mime = $variants['web_mime'];
            $post->attachment_web_mime = $variants['web_mime'];
            $post->attachment_original_mime = $variants['original_mime'];
            $post->attachment_size = $variants['web_size'];
            $post->attachment_web_size = $variants['web_size'];
            $post->attachment_original_size = $variants['original_size'];
            $post->attachment_web_width = $variants['width'];
            $post->attachment_web_height = $variants['height'];
            $post->attachment_variants_json = $variants['variants_json'];
        } else {
            $path = $this->mediaStorage->storePostAttachment($attachment, (int) $post->id);
            $size = (int) ($attachment->getSize() ?? 0);
            $mimeValue = (string) ($attachment->getClientMimeType() ?: $attachment->getMimeType());

            $post->attachment_path = $path;
            $post->attachment_mime = $mimeValue;
            $post->attachment_size = $size;
            $post->attachment_web_path = null;
            $post->attachment_original_path = null;
            $post->attachment_web_mime = null;
            $post->attachment_original_mime = null;
            $post->attachment_web_size = null;
            $post->attachment_original_size = null;
            $post->attachment_web_width = null;
            $post->attachment_web_height = null;
            $post->attachment_variants_json = null;
        }

        $post->attachment_moderation_status = ($moderationEnabled && $isImageAttachment)
            ? 'pending'
            : null;
        $post->attachment_is_blurred = $moderationEnabled && $isImageAttachment;
        $post->save();
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
