<?php

namespace App\Services;

use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class FeedQueryBuilder
{
    public function build(array $options, ?User $viewer = null): Builder
    {
        $kind = (string) ($options['kind'] ?? 'roots');
        $withCounts = (bool) ($options['with_counts'] ?? false);
        $includeHidden = (bool) ($options['include_hidden'] ?? false);
        $tag = isset($options['tag']) ? strtolower((string) $options['tag']) : null;
        $order = (string) ($options['order'] ?? 'created_desc');
        $sourcesInclude = $options['sources_include'] ?? null;
        $sourcesExclude = $options['sources_exclude'] ?? null;
        $pinned = $options['pinned'] ?? null; // only|exclude|null
        $isAdmin = $viewer?->isAdmin() ?? false;

        $query = Post::query()->with([
            'user:id,name,username,location,bio,is_admin,avatar_path',
            'replies.user:id,name,username,location,bio,is_admin,avatar_path',
            'parent.user:id,name,username,location,bio,is_admin,avatar_path',
            'tags:id,name',
            'hashtags:id,name',
        ]);

        if ($withCounts) {
            $query->withCount(['likes', 'replies']);
        } else {
            $query->withCount('likes');
        }

        if ($viewer) {
            $query->withExists([
                'likes as liked_by_me' => fn ($likesQuery) => $likesQuery->where('user_id', $viewer->id),
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
                ->orderByRaw('CASE WHEN pinned_at IS NULL THEN 0 ELSE 1 END DESC')
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
