<?php

namespace App\Http\Controllers\Api;

use App\Enums\PostAuthorKind;
use App\Enums\PostBotIdentity;
use App\Http\Controllers\Controller;
use App\Http\Requests\Post\ReplyPostRequest;
use App\Http\Requests\Post\StorePostRequest;
use App\Models\Post;
use App\Services\PostPayloadService;
use App\Services\PostService;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;

class PostController extends Controller
{
    public function __construct(
        private readonly PostService $posts,
        private readonly PostPayloadService $payloads,
    ) {
    }

    public function index(Request $request)
    {
        $viewer = $request->user() ?? $request->user('sanctum');

        if ($request->query('scope') === 'me' && !$viewer) {
            return response()->json([
                'message' => 'Neprihlaseny pouzivatel.',
            ], 401);
        }

        $result = $this->posts->getPaginatedFeed([
            'per_page' => $request->query('per_page', 20),
            'kind' => $request->query('kind', 'roots'),
            'with_counts' => $request->query('with') === 'counts',
            'include_hidden' => $request->boolean('include_hidden'),
            'scope' => $request->query('scope'),
            'source' => $request->query('source'),
            'tag' => $request->query('tag'),
        ], $viewer);

        return response()->json(
            $this->payloads->serializePaginator($result, $viewer)
        );
    }

    public function show(Request $request, Post $post)
    {
        $viewer = $this->resolveViewer($request);
        $canViewRestricted = $viewer ? Gate::forUser($viewer)->allows('viewRestricted', $post) : false;

        if (($post->is_hidden || $post->hidden_at || $post->moderation_status === 'blocked') && !$canViewRestricted) {
            return response()->json([
                'message' => 'Nenaslo sa.',
            ], 404);
        }

        try {
            $payload = $this->posts->getThread($post, $viewer);
        } catch (ModelNotFoundException) {
            return response()->json([
                'message' => 'Nenaslo sa.',
            ], 404);
        }

        return response()->json([
            'post' => $payload['post'] ? $this->payloads->serializePost($payload['post'], $viewer) : null,
            'root' => $payload['root'] ? $this->payloads->serializePost($payload['root'], $viewer) : null,
            'thread' => $this->payloads->serializeCollection($payload['thread'], $viewer)->values(),
            'replies' => $this->payloads->serializeNestedReplies($payload['replies'], $viewer),
        ]);
    }

    public function destroy(Request $request, Post $post)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Neprihlaseny pouzivatel.',
            ], 401);
        }

        if (Gate::forUser($user)->denies('delete', $post)) {
            return response()->json([
                'message' => 'Nemate opravnenie zmazat tento post.',
            ], 403);
        }

        $this->posts->deletePost($post);

        return response()->noContent();
    }

    public function update(Request $request, Post $post)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Neprihlaseny pouzivatel.',
            ], 401);
        }

        if (Gate::forUser($user)->denies('update', $post)) {
            return response()->json([
                'message' => 'Nemate opravnenie upravit tento post.',
            ], 403);
        }

        if (!$this->canAdminEditBotPost($user, $post)) {
            return response()->json([
                'message' => 'Tento post nie je povolene upravovat.',
            ], 403);
        }

        $validated = $request->validate([
            'content' => ['required', 'string', 'max:5000'],
            'edit_variant' => ['nullable', 'in:translated'],
        ]);

        $content = trim((string) $validated['content']);

        $post->content = $content;
        $post->meta = $this->updatedBotMetaAfterEdit($post, $content);
        $post->save();

        $post->refresh();

        return response()->json($this->payloads->serializePost($post, $user));
    }

    public function store(StorePostRequest $request)
    {
        $post = $this->posts->createPost(
            $request->user(),
            $request->validated('content'),
            $request->file('attachment'),
            $request->validated('poll'),
            $request->postAttributes()
        );

        return response()->json($this->payloads->serializePost($post, $request->user()), 201);
    }

    public function reply(ReplyPostRequest $request, Post $post)
    {
        if ($this->posts->getReplyDepth($post) > 2) {
            return response()->json([
                'message' => 'Max depth je 2 (root -> reply -> reply).',
            ], 422);
        }

        $reply = $this->posts->createReply(
            $request->user(),
            $post,
            $request->validated('content'),
            $request->file('attachment'),
            $request->replyAttributes()
        );

        return response()->json($this->payloads->serializePost($reply, $request->user()), 201);
    }

    public function like(Request $request, Post $post)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Neprihlaseny pouzivatel.',
            ], 401);
        }

        return response()->json($this->posts->likePost($post, $user));
    }

    public function unlike(Request $request, Post $post)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Neprihlaseny pouzivatel.',
            ], 401);
        }

        return response()->json($this->posts->unlikePost($post, $user));
    }

    public function view(Request $request, Post $post)
    {
        $viewer = $this->resolveViewer($request);
        $canViewRestricted = $viewer ? Gate::forUser($viewer)->allows('viewRestricted', $post) : false;

        if (($post->is_hidden || $post->hidden_at || $post->moderation_status === 'blocked') && !$canViewRestricted) {
            return response()->json([
                'message' => 'Nenaslo sa.',
            ], 404);
        }

        $viewerKey = $viewer
            ? 'u:' . $viewer->id
            : $this->resolveAnonymousViewerKey($request);

        $cacheKey = sprintf('post_view:%d:%s', $post->id, $viewerKey);
        $incremented = Cache::add($cacheKey, 1, now()->addMinutes(15));

        if ($incremented) {
            Post::query()->whereKey($post->id)->increment('views');
        }

        $views = (int) Post::query()->whereKey($post->id)->value('views');

        return response()->json([
            'views' => $views,
            'incremented' => (bool) $incremented,
        ]);
    }

    private function resolveAnonymousViewerKey(Request $request): string
    {
        if (method_exists($request, 'hasSession') && $request->hasSession()) {
            $sessionId = $request->session()->getId();
            if ($sessionId) {
                return 's:' . $sessionId;
            }
        }

        $ip = (string) $request->ip();
        $ua = (string) $request->userAgent();

        return 'ip:' . sha1($ip . '|' . substr($ua, 0, 120));
    }

    private function resolveViewer(Request $request): ?User
    {
        return $request->user() ?? $request->user('sanctum');
    }

    private function canAdminEditBotPost(User $user, Post $post): bool
    {
        if (!$user->isAdmin()) {
            return false;
        }

        $authorKind = $post->author_kind;
        $identity = $post->bot_identity;

        $isBotAuthor = $authorKind instanceof PostAuthorKind
            ? $authorKind === PostAuthorKind::BOT
            : strtolower((string) $authorKind) === PostAuthorKind::BOT->value;

        if (!$isBotAuthor) {
            return false;
        }

        $normalizedIdentity = $identity instanceof PostBotIdentity
            ? $identity->value
            : strtolower(trim((string) $identity));

        return in_array($normalizedIdentity, [
            PostBotIdentity::KOZMO->value,
            PostBotIdentity::STELA->value,
        ], true);
    }

    private function updatedBotMetaAfterEdit(Post $post, string $content): array
    {
        $meta = is_array($post->meta) ? $post->meta : [];

        if ($meta === []) {
            return $meta;
        }

        // Admin edit updates only translated variant; original text stays immutable.
        $meta['translated_content'] = $content;
        $meta['used_translation'] = true;

        return $meta;
    }
}

