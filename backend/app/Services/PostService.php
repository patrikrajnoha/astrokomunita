<?php

namespace App\Services;

use App\Models\Post;
use App\Models\User;
use App\Support\HashtagParser;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PostService
{
    public function __construct(
        private readonly NotificationService $notifications,
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
        $isAdmin = $user?->isAdmin() ?? false;

        $query = Post::query()
            ->with([
                'user:id,name,username,location,bio,is_admin,avatar_path',
                'replies.user:id,name,username,location,bio,is_admin,avatar_path',
                'parent.user:id,name,username,location,bio,is_admin,avatar_path',
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
                'likes as liked_by_me' => fn ($likesQuery) => $likesQuery->where('user_id', $user->id),
            ]);
        }

        if ($kind === 'replies') {
            $query->whereNotNull('parent_id')->with([
                'parent.user:id,name,username,location,bio,is_admin,avatar_path',
            ]);
        } elseif ($kind === 'media') {
            $query->whereNotNull('attachment_path')->with([
                'parent.user:id,name,username,location,bio,is_admin,avatar_path',
            ]);
        } else {
            $query->whereNull('parent_id');
        }

        if (!$includeHidden || !$isAdmin) {
            $query->where('is_hidden', false);
        }

        $query->notExpired();

        $query->where(function ($sourceFilterQuery) {
            $sourceFilterQuery->whereNull('source_name')
                ->orWhereNotIn('source_name', ['astrobot', 'nasa_rss']);
        });

        if ($scope === 'me' && $user) {
            $query->where('user_id', $user->id);
            $query->where(function ($sourceFilterQuery) {
                $sourceFilterQuery->whereNull('source_name')
                    ->orWhereNotIn('source_name', ['astrobot', 'nasa_rss']);
            });
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

        if ($tag) {
            $query->whereHas('tags', function ($tagsQuery) use ($tag) {
                $tagsQuery->where('name', $tag)->orWhere('slug', $tag);
            });
        }

        return $query->paginate($perPage)->withQueryString();
    }

    public function getThread(Post $post, ?User $viewer): array
    {
        $isAdmin = $viewer?->isAdmin() ?? false;
        $root = $this->resolveRootPost($post);

        if ($root->is_hidden && !$isAdmin) {
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
            $threadQuery->where('is_hidden', false);
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
            $post = new Post();
            $post->user_id = $user->id;
            $post->content = $content;
            $post->parent_id = null;
            $post->root_id = null;
            $post->depth = 0;
            $post->is_hidden = false;

            if ($attachment) {
                $path = $attachment->store('posts', 'public');
                $post->attachment_path = $path;
                $post->attachment_mime = $attachment->getClientMimeType();
                $post->attachment_original_name = $attachment->getClientOriginalName();
                $post->attachment_size = $attachment->getSize();
            }

            $post->save();

            HashtagParser::syncHashtags($post, $content);

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

            if ($attachment) {
                $path = $attachment->store('posts', 'public');
                $reply->attachment_path = $path;
                $reply->attachment_mime = $attachment->getClientMimeType();
                $reply->attachment_original_name = $attachment->getClientOriginalName();
                $reply->attachment_size = $attachment->getSize();
            }

            $reply->save();

            HashtagParser::syncHashtags($reply, $content);

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
}
