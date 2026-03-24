<?php

namespace App\Services;

use App\Enums\PostAuthorKind;
use App\Enums\PostFeedKey;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class FeedQueryBuilder
{
    public function __construct(
        private readonly PollService $polls,
    ) {
    }

    public function build(array $options, ?User $viewer = null): Builder
    {
        $kind = (string) ($options['kind'] ?? 'roots');
        $withCounts = (bool) ($options['with_counts'] ?? false);
        $includeHidden = (bool) ($options['include_hidden'] ?? false);
        $tag = isset($options['tag']) ? strtolower((string) $options['tag']) : null;
        $order = (string) ($options['order'] ?? 'created_desc');
        $feedKey = strtolower((string) ($options['feed_key'] ?? PostFeedKey::COMMUNITY->value));
        $authorKind = $options['author_kind'] ?? null;
        $sourcesInclude = $options['sources_include'] ?? null;
        $sourcesExclude = $options['sources_exclude'] ?? null;
        $pinned = $options['pinned'] ?? null; // only|exclude|null
        $isAdmin = $viewer?->isAdmin() ?? false;

        $query = Post::query()->with(array_merge([
            'user:id,name,username,location,bio,is_admin,is_bot,role,avatar_path,avatar_mode,avatar_color,avatar_icon,avatar_seed',
            'replies.user:id,name,username,location,bio,is_admin,is_bot,role,avatar_path,avatar_mode,avatar_color,avatar_icon,avatar_seed',
            'parent.user:id,name,username,location,bio,is_admin,is_bot,role,avatar_path,avatar_mode,avatar_color,avatar_icon,avatar_seed',
            'tags:id,name',
            'hashtags:id,name',
        ], $this->polls->pollRelations($viewer?->id)));

        if ($withCounts) {
            $query->withCount(['likes', 'replies']);
        } else {
            $query->withCount('likes');
        }

        if ($viewer) {
            $query->withExists([
                'likes as liked_by_me' => fn ($likesQuery) => $likesQuery->where('user_id', $viewer->id),
                'bookmarkedBy as is_bookmarked' => fn ($bookmarksQuery) => $bookmarksQuery->where('user_id', $viewer->id),
            ]);
        }

        if (!in_array($feedKey, [PostFeedKey::COMMUNITY->value, PostFeedKey::ASTRO->value], true)) {
            $feedKey = PostFeedKey::COMMUNITY->value;
        }
        $query->where('feed_key', $feedKey);

        if (is_string($authorKind) && in_array($authorKind, [PostAuthorKind::USER->value, PostAuthorKind::BOT->value], true)) {
            $query->where('author_kind', $authorKind);
        }

        if ($kind === 'replies') {
            $query->whereNotNull('parent_id')->with([
                'parent.user:id,name,username,location,bio,is_admin,is_bot,role,avatar_path,avatar_mode,avatar_color,avatar_icon,avatar_seed',
            ]);
        } elseif ($kind === 'events') {
            $query
                ->whereNull('parent_id')
                ->whereNotNull('meta->event->event_id');
        } elseif ($kind === 'gifs') {
            $query
                ->whereNull('parent_id')
                ->where(function (Builder $gifQuery): void {
                    $gifQuery
                        ->whereNotNull('meta->gif->id')
                        ->orWhereNotNull('meta->gif->original_url')
                        ->orWhereNotNull('meta->gif->preview_url');
                });
        } elseif ($kind === 'media') {
            $query
                ->where(function (Builder $mediaQuery): void {
                    $mediaQuery
                        ->whereNotNull('attachment_path')
                        ->orWhereNotNull('meta->gif->id')
                        ->orWhereNotNull('meta->gif->original_url')
                        ->orWhereNotNull('meta->gif->preview_url');
                })
                ->with([
                    'parent.user:id,name,username,location,bio,is_admin,is_bot,role,avatar_path,avatar_mode,avatar_color,avatar_icon,avatar_seed',
                ]);
        } else {
            $query->whereNull('parent_id');
        }

        if ($pinned === 'only') {
            $query->whereNotNull('pinned_at');
        } elseif ($pinned === 'exclude') {
            $query->whereNull('pinned_at');
        }

        if (is_array($sourcesInclude) && count($sourcesInclude) > 0) {
            $query->whereIn('source_name', $sourcesInclude);
        }

        if (is_array($sourcesExclude) && count($sourcesExclude) > 0) {
            $query->where(function ($sourceFilterQuery) use ($sourcesExclude) {
                $sourceFilterQuery->whereNull('source_name')
                    ->orWhereNotIn('source_name', $sourcesExclude);
            });
        }

        if (!$includeHidden || !$isAdmin) {
            $query->publiclyVisible();
        }

        $query->notExpired();

        if ($tag) {
            $query->whereHas('tags', function ($tagsQuery) use ($tag) {
                $tagsQuery->where('name', $tag)->orWhere('slug', $tag);
            });
        }

        if ($order === 'pinned_then_created') {
            $query
                ->orderByRaw('CASE WHEN pinned_at IS NULL THEN 1 ELSE 0 END')
                ->orderBy('pinned_at', 'desc')
                ->orderBy('created_at', 'desc')
                ->orderBy('id', 'desc');
        } elseif ($order === 'profile_pinned_then_created') {
            $query
                ->orderByRaw('CASE WHEN profile_pinned_at IS NULL THEN 1 ELSE 0 END')
                ->orderBy('profile_pinned_at', 'desc')
                ->orderBy('created_at', 'desc')
                ->orderBy('id', 'desc');
        } elseif ($order === 'pinned_desc') {
            $query->orderBy('pinned_at', 'desc');
        } else {
            $query
                ->orderBy('created_at', 'desc')
                ->orderBy('id', 'desc');
        }

        return $query;
    }
}
