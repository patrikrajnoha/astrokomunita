<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\Request;

class FeedController extends Controller
{
    /**
     * GET /api/feed
     * 
     * Main public feed - combines user posts and AstroBot posts
     * Pinned posts always appear first (both user and AstroBot)
     * Supports pagination, filtering, and same parameters as other feeds
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

        // Get pinned posts first (both user and AstroBot)
        $pinnedQuery = Post::query()
            ->whereNotNull('pinned_at')
            ->with([
                'user:id,name,username,email,location,bio,is_admin,avatar_path',
                'replies.user:id,name,username,email,location,bio,is_admin,avatar_path',
                'parent.user:id,name,username,email,location,bio,is_admin,avatar_path',
                'tags:id,name',
                'hashtags:id,name',
            ])
            ->withCount('likes')
            ->orderBy('pinned_at', 'desc');

        // Apply common filters to pinned posts
        if ($user) {
            $pinnedQuery->withExists([
                'likes as liked_by_me' => fn ($q) => $q->where('user_id', $user->id),
            ]);
        }

        if ($kind === 'replies') {
            $pinnedQuery->whereNotNull('parent_id');
        } elseif ($kind === 'media') {
            $pinnedQuery->whereNotNull('attachment_path');
        } else {
            $pinnedQuery->whereNull('parent_id');
        }

        if (!$request->boolean('include_hidden') || !$isAdmin) {
            $pinnedQuery->where('is_hidden', false);
        }

        $pinnedQuery->notExpired();

        // Get regular posts (excluding pinned to avoid duplication)
        // Include both user posts and AstroBot posts (except hidden/expired)
        $regularQuery = Post::query()
            ->whereNull('pinned_at')
            ->where(function ($q) {
                $q->whereNull('source_name')
                  ->orWhereNotIn('source_name', ['astrobot', 'nasa_rss'])
                  ->orWhere(function ($subQ) {
                      // Include AstroBot posts that are not hidden and not expired
                      $subQ->whereIn('source_name', ['astrobot', 'nasa_rss'])
                           ->where('is_hidden', false);
                  });
            })
            ->with([
                'user:id,name,username,email,location,bio,is_admin,avatar_path',
                'replies.user:id,name,username,email,location,bio,is_admin,avatar_path',
                'parent.user:id,name,username,email,location,bio,is_admin,avatar_path',
                'tags:id,name',
                'hashtags:id,name',
            ])
            ->orderBy('created_at', 'desc');

        $counts = ['likes'];
        if ($withCounts) {
            $counts[] = 'replies';
        }
        $regularQuery->withCount($counts);

        if ($user) {
            $regularQuery->withExists([
                'likes as liked_by_me' => fn ($q) => $q->where('user_id', $user->id),
            ]);
        }

        if ($kind === 'replies') {
            $regularQuery->whereNotNull('parent_id');
        } elseif ($kind === 'media') {
            $regularQuery->whereNotNull('attachment_path');
        } else {
            $regularQuery->whereNull('parent_id');
        }

        if (!$request->boolean('include_hidden') || !$isAdmin) {
            $regularQuery->where('is_hidden', false);
        }

        $regularQuery->notExpired();

        // Filter by tag - try both name and slug for robustness
        if ($tag = $request->query('tag')) {
            $tag = strtolower($tag);
            $pinnedQuery->whereHas('tags', function ($q) use ($tag) {
                $q->where('name', $tag)->orWhere('slug', $tag);
            });
            $regularQuery->whereHas('tags', function ($q) use ($tag) {
                $q->where('name', $tag)->orWhere('slug', $tag);
            });
        }

        // Get pinned posts and regular posts
        $pinnedPosts = $pinnedQuery->get();
        $regularQuery = $regularQuery->paginate($perPage - $pinnedPosts->count())->withQueryString();
        
        // Combine results: pinned first, then regular
        $allPosts = $pinnedPosts->concat($regularQuery->getCollection());
        
        // Create custom pagination metadata
        $result = $regularQuery;
        $result->setCollection($allPosts);

        return response()->json($result);
    }

    /**
     * GET /api/feed/astrobot
     * 
     * Returns paginated AstroBot posts only.
     * Supports same pagination parameters as main feed.
     */
    public function astrobot(Request $request)
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
            ->whereIn('source_name', ['astrobot', 'nasa_rss'])
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

        // Exclude expired AstroBot posts
        $query->notExpired();

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
}
