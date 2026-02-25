<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EventResource;
use App\Models\BlogPost;
use App\Models\Event;
use App\Models\Hashtag;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class SearchController extends Controller
{
    /**
     * Unified search suggestions for autocomplete.
     */
    public function suggest(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:2|max:255',
            'limit' => 'nullable|integer|min:1|max:10',
        ]);

        $query = trim((string) $request->get('q', ''));
        $limit = (int) $request->get('limit', 8);
        $limit = max(1, min($limit, 10));

        if ($query === '' || mb_strlen($query) < 2) {
            return response()->json(['data' => []]);
        }

        $usersLimit = min(5, $limit);
        $users = User::query()
            ->where('is_active', true)
            ->where('is_banned', false)
            ->where(function ($q) use ($query) {
                $q->where('username', 'LIKE', $query . '%')
                    ->orWhere('name', 'LIKE', $query . '%');
            })
            ->select(['id', 'name', 'username'])
            ->orderBy('username')
            ->limit($usersLimit)
            ->get();

        $remaining = max(0, $limit - $users->count());
        $tags = collect();

        if ($remaining > 0) {
            $tags = Tag::query()
                ->where('name', 'LIKE', $query . '%')
                ->withCount('posts')
                ->orderByDesc('posts_count')
                ->orderBy('name')
                ->limit($remaining)
                ->get(['id', 'name']);
        }

        $suggestions = collect();

        foreach ($users as $user) {
            $displayName = trim((string) $user->name);
            $username = trim((string) $user->username);
            $label = $displayName !== ''
                ? sprintf('%s (@%s)', $displayName, $username)
                : '@' . $username;

            $suggestions->push([
                'id' => (string) $user->id,
                'type' => 'user',
                'label' => $label,
                'value' => $username,
            ]);
        }

        foreach ($tags as $tag) {
            $name = trim((string) $tag->name);
            $suggestions->push([
                'id' => (string) $tag->id,
                'type' => 'tag',
                'label' => '#' . $name,
                'value' => '#' . $name,
            ]);
        }

        return response()->json([
            'data' => $suggestions->take($limit)->values(),
        ]);
    }

    /**
     * Search users by username or name (prefix, case-insensitive).
     */
    public function users(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:1|max:255',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        $query = $request->get('q');
        $limit = $request->get('limit', 20);

        $users = User::query()
            ->where('is_active', true)
            ->where('is_banned', false)
            ->where(function ($q) use ($query) {
                $q->where('username', 'LIKE', $query . '%')
                    ->orWhere('name', 'LIKE', $query . '%');
            })
            ->select(['id', 'name', 'username', 'avatar_path'])
            ->orderBy('username')
            ->limit($limit)
            ->get();

        return response()->json([
            'data' => $users,
            'total' => $users->count(),
        ]);
    }

    /**
     * Search root posts by content.
     */
    public function posts(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:1|max:255',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        $query = $request->get('q');
        $limit = $request->get('limit', 20);

        $posts = Post::query()
            ->whereNull('parent_id')
            ->publiclyVisible()
            ->where(function ($q) use ($query) {
                $q->where('content', 'LIKE', '%' . $query . '%');
            })
            ->with(['user:id,name,username,avatar_path'])
            ->withCount(['likes', 'replies'])
            ->latest()
            ->limit($limit)
            ->get();

        return response()->json([
            'data' => $posts,
            'total' => $posts->count(),
        ]);
    }

    public function events(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:1|max:255',
            'limit' => 'nullable|integer|min:1|max:30',
        ]);

        $query = trim((string) $request->get('q', ''));
        $limit = max(1, min((int) $request->get('limit', 12), 30));

        $events = Event::query()
            ->published()
            ->where(function (Builder $builder) use ($query): void {
                $builder->where('title', 'like', '%' . $query . '%')
                    ->orWhere('short', 'like', '%' . $query . '%')
                    ->orWhere('description', 'like', '%' . $query . '%');
            })
            ->withCount(['favorites', 'invites', 'reminders'])
            ->orderByRaw('(favorites_count * 4 + invites_count * 3 + reminders_count * 3) desc')
            ->orderByRaw('coalesce(start_at, max_at) asc')
            ->limit($limit)
            ->get();

        return response()->json([
            'data' => EventResource::collection($events)->resolve(),
            'total' => $events->count(),
        ]);
    }

    public function articles(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:1|max:255',
            'limit' => 'nullable|integer|min:1|max:30',
        ]);

        $query = trim((string) $request->get('q', ''));
        $limit = max(1, min((int) $request->get('limit', 10), 30));

        $articles = BlogPost::query()
            ->published()
            ->where(function (Builder $builder) use ($query): void {
                $builder->where('title', 'like', '%' . $query . '%')
                    ->orWhere('content', 'like', '%' . $query . '%');
            })
            ->withCount('comments')
            ->with(['tags:id,name,slug'])
            ->orderByRaw('(views * 0.8 + comments_count * 2) desc')
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get([
                'id',
                'title',
                'slug',
                'published_at',
                'views',
            ]);

        return response()->json([
            'data' => $articles,
            'total' => $articles->count(),
        ]);
    }

    public function keywords(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'nullable|string|max:255',
            'limit' => 'nullable|integer|min:1|max:30',
        ]);

        $query = trim((string) $request->get('q', ''));
        $limit = max(1, min((int) $request->get('limit', 12), 30));

        $hashtags = Hashtag::query()
            ->when($query !== '', fn (Builder $builder) => $builder->where('name', 'like', $query . '%'))
            ->withCount('posts')
            ->orderByDesc('posts_count')
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'name']);

        $items = $hashtags->map(static fn (Hashtag $hashtag): array => [
            'id' => $hashtag->id,
            'value' => '#' . $hashtag->name,
            'posts_count' => (int) $hashtag->posts_count,
            'source' => 'hashtag',
        ])->values();

        return response()->json([
            'data' => $items,
            'total' => $items->count(),
        ]);
    }

    public function global(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:1|max:255',
            'limit' => 'nullable|integer|min:1|max:20',
        ]);

        $query = trim((string) $request->get('q', ''));
        $limit = max(1, min((int) $request->get('limit', 6), 20));

        $users = User::query()
            ->where('is_active', true)
            ->where('is_banned', false)
            ->where(function (Builder $builder) use ($query): void {
                $builder->where('username', 'like', $query . '%')
                    ->orWhere('name', 'like', $query . '%');
            })
            ->select(['id', 'name', 'username', 'avatar_path'])
            ->orderBy('username')
            ->limit($limit)
            ->get();

        $posts = Post::query()
            ->whereNull('parent_id')
            ->publiclyVisible()
            ->notExpired()
            ->where('content', 'like', '%' . $query . '%')
            ->with(['user:id,name,username,avatar_path'])
            ->withCount(['likes', 'replies'])
            ->orderByRaw('(likes_count * 3 + replies_count * 2 + views * 0.2) desc')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        $events = Event::query()
            ->published()
            ->where(function (Builder $builder) use ($query): void {
                $builder->where('title', 'like', '%' . $query . '%')
                    ->orWhere('short', 'like', '%' . $query . '%')
                    ->orWhere('description', 'like', '%' . $query . '%');
            })
            ->withCount(['favorites', 'invites', 'reminders'])
            ->orderByRaw('(favorites_count * 4 + invites_count * 3 + reminders_count * 3) desc')
            ->orderByRaw('coalesce(start_at, max_at) asc')
            ->limit($limit)
            ->get();

        $articles = BlogPost::query()
            ->published()
            ->where(function (Builder $builder) use ($query): void {
                $builder->where('title', 'like', '%' . $query . '%')
                    ->orWhere('content', 'like', '%' . $query . '%');
            })
            ->withCount('comments')
            ->orderByRaw('(views * 0.8 + comments_count * 2) desc')
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get(['id', 'title', 'slug', 'published_at', 'views']);

        $keywords = Hashtag::query()
            ->where('name', 'like', $query . '%')
            ->withCount('posts')
            ->orderByDesc('posts_count')
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'name'])
            ->map(static fn (Hashtag $hashtag): array => [
                'id' => $hashtag->id,
                'value' => '#' . $hashtag->name,
                'posts_count' => (int) $hashtag->posts_count,
                'source' => 'hashtag',
            ])
            ->values();

        return response()->json([
            'data' => [
                'users' => $users,
                'posts' => $posts,
                'events' => EventResource::collection($events)->resolve(),
                'articles' => $articles,
                'keywords' => $keywords,
            ],
        ]);
    }

    public function discovery(Request $request): JsonResponse
    {
        $request->validate([
            'limit_events' => 'nullable|integer|min:1|max:12',
            'limit_posts' => 'nullable|integer|min:1|max:12',
        ]);

        $eventsLimit = max(1, min((int) $request->get('limit_events', 9), 12));
        $postsLimit = max(1, min((int) $request->get('limit_posts', 3), 12));
        $last24h = Carbon::now()->subDay();

        $topEvents = Event::query()
            ->published()
            ->where(function (Builder $builder): void {
                $builder->whereNotNull('start_at')->orWhereNotNull('max_at');
            })
            ->withCount(['favorites', 'invites', 'reminders'])
            ->orderByRaw('(favorites_count * 4 + invites_count * 3 + reminders_count * 3) desc')
            ->orderByRaw('coalesce(start_at, max_at) asc')
            ->limit($eventsLimit)
            ->get();

        $hotPosts = Post::query()
            ->whereNull('parent_id')
            ->publiclyVisible()
            ->notExpired()
            ->with(['user:id,name,username,avatar_path'])
            ->withCount(['likes', 'replies'])
            ->where('created_at', '>=', $last24h)
            ->orderByRaw('(likes_count * 3 + replies_count * 2 + views * 0.2) desc')
            ->orderByDesc('created_at')
            ->limit($postsLimit)
            ->get();

        if ($hotPosts->count() < $postsLimit) {
            $missing = $postsLimit - $hotPosts->count();
            $fallback = Post::query()
                ->whereNull('parent_id')
                ->publiclyVisible()
                ->notExpired()
                ->whereNotIn('id', $hotPosts->pluck('id')->all())
                ->with(['user:id,name,username,avatar_path'])
                ->withCount(['likes', 'replies'])
                ->orderByRaw('(likes_count * 3 + replies_count * 2 + views * 0.2) desc')
                ->orderByDesc('created_at')
                ->limit($missing)
                ->get();

            $hotPosts = $hotPosts->concat($fallback)->values();
        }

        $newsPosts = Post::query()
            ->whereNull('parent_id')
            ->publiclyVisible()
            ->notExpired()
            ->where(function (Builder $builder): void {
                $builder->where('author_kind', 'bot')
                    ->orWhereNotNull('source_name');
            })
            ->with(['user:id,name,username,avatar_path'])
            ->withCount(['likes', 'replies'])
            ->orderByDesc('created_at')
            ->limit(6)
            ->get();

        $newsArticles = BlogPost::query()
            ->published()
            ->withCount('comments')
            ->orderByDesc('published_at')
            ->limit(6)
            ->get(['id', 'title', 'slug', 'published_at', 'views']);

        $keywords = Hashtag::query()
            ->withCount('posts')
            ->orderByDesc('posts_count')
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name'])
            ->map(static fn (Hashtag $hashtag): array => [
                'id' => $hashtag->id,
                'value' => '#' . $hashtag->name,
                'posts_count' => (int) $hashtag->posts_count,
                'source' => 'hashtag',
            ])
            ->values();

        return response()->json([
            'data' => [
                'trending' => [
                    'events' => EventResource::collection($topEvents->take(3))->resolve(),
                    'posts' => $hotPosts->take(3)->values(),
                ],
                'news' => [
                    'posts' => $newsPosts,
                    'articles' => $newsArticles,
                ],
                'events' => [
                    'events' => EventResource::collection($topEvents)->resolve(),
                    'posts' => $hotPosts,
                ],
                'keywords' => $keywords,
            ],
        ]);
    }
}
