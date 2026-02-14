<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\FeedQueryBuilder;
use App\Models\User;

class FeedController extends Controller
{
    public function __construct(
        private readonly FeedQueryBuilder $feedQueryBuilder,
    ) {
    }

    /**
     * GET /api/feed
     * 
     * Main public feed - user posts only (AstroBot excluded)
     * Pinned posts always appear first
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
            'order' => 'pinned_then_created',
            'sources_exclude' => ['astrobot', 'nasa_rss'],
            'tag' => $tag ? strtolower((string) $tag) : null,
        ], $user);

        return response()->json(
            $query->paginate($perPage)->withQueryString()
        );
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

        $user = $this->resolveViewer($request);
        $query = $this->feedQueryBuilder->build([
            'kind' => $kind,
            'with_counts' => $withCounts,
            'include_hidden' => $request->boolean('include_hidden'),
            'order' => 'pinned_then_created',
            'sources_include' => ['astrobot', 'nasa_rss'],
        ], $user);

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

    private function resolveViewer(Request $request): ?User
    {
        return $request->user() ?? $request->user('sanctum');
    }
}
