<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\FeedQueryBuilder;
use App\Services\PostPayloadService;
use App\Enums\PostAuthorKind;
use App\Enums\PostFeedKey;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class FeedController extends Controller
{
    public function __construct(
        private readonly FeedQueryBuilder $feedQueryBuilder,
        private readonly PostPayloadService $payloads,
    ) {
    }

    /**
     * GET /api/feed
     *
     * Main community feed.
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
        $tag = $request->query('tag');

        $user = $this->resolveViewer($request);
        $query = $this->feedQueryBuilder->build([
            'kind' => $kind,
            'with_counts' => $withCounts,
            'include_hidden' => $request->boolean('include_hidden'),
            'feed_key' => PostFeedKey::COMMUNITY->value,
            'order' => 'created_desc',
            'tag' => $tag ? strtolower((string) $tag) : null,
        ], $user);

        $paginator = $query->paginate($perPage)->withQueryString();

        return response()->json(
            $this->payloads->serializePaginator($paginator, $user)
        );
    }

    /**
     * GET /api/feed/astrobot
     * Legacy alias for Astro feed.
     */
    public function astrobot(Request $request)
    {
        $cacheKey = 'deprecated:feed:astrobot';
        if (Cache::add($cacheKey, true, now()->addMinutes(5))) {
            Log::warning('DEPRECATED: /api/feed/astrobot used', [
                'user_id' => $request->user()?->id,
                'ip' => $request->ip(),
                'user_agent' => $this->truncateText((string) $request->userAgent(), 120),
            ]);
        }

        // TODO(2026-06-30): Remove legacy /api/feed/astrobot alias after migration window.
        $response = $this->astro($request);
        $response->headers->set('X-Deprecated-Endpoint', '/api/feed/astrobot');
        $response->headers->set('X-Deprecated-Successor', '/api/astro-feed');
        $response->headers->set('Sunset', '2026-06-30');

        return $response;
    }

    /**
     * GET /api/astro-feed
     *
     * Returns paginated AstroFeed bot posts only.
     * Supports same pagination parameters as main feed.
     */
    public function astro(Request $request)
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

        $user = $this->resolveViewer($request);
        $query = $this->feedQueryBuilder->build([
            'kind' => $kind,
            'with_counts' => $withCounts,
            'include_hidden' => $request->boolean('include_hidden'),
            'feed_key' => PostFeedKey::ASTRO->value,
            'author_kind' => PostAuthorKind::BOT->value,
            'order' => 'created_desc',
        ], $user);

        // Filter by tag - try both name and slug for robustness
        if ($tag = $request->query('tag')) {
            $tag = strtolower($tag);
            $query->whereHas('tags', function ($q) use ($tag) {
                $q->where('name', $tag)->orWhere('slug', $tag);
            });
        }

        $paginator = $query->paginate($perPage)->withQueryString();

        return response()->json(
            $this->payloads->serializePaginator($paginator, $user)
        );
    }

    private function resolveViewer(Request $request): ?User
    {
        return $request->user() ?? $request->user('sanctum');
    }

    private function truncateText(string $value, int $maxLength): string
    {
        $normalized = trim($value);
        if ($normalized === '' || $maxLength <= 0) {
            return '';
        }

        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($normalized) <= $maxLength) {
                return $normalized;
            }

            return mb_substr($normalized, 0, $maxLength);
        }

        if (strlen($normalized) <= $maxLength) {
            return $normalized;
        }

        return substr($normalized, 0, $maxLength);
    }
}
