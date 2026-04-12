<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Models\Event;
use App\Models\Post;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class SitemapController extends Controller
{
    private const BASE_URL = 'https://astrokomunita.sk';
    private const CACHE_TTL = 3600; // 1 hour — auto-updating without hammering the DB

    public function __invoke(): Response
    {
        $xml = Cache::remember('sitemap:v1', self::CACHE_TTL, fn () => $this->build());

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=utf-8',
            'X-Robots-Tag' => 'noindex',
        ]);
    }

    private function build(): string
    {
        $urls = collect();

        // Static pages
        $urls->push(['loc' => self::BASE_URL . '/',         'priority' => '1.0', 'changefreq' => 'daily']);
        $urls->push(['loc' => self::BASE_URL . '/events',   'priority' => '0.9', 'changefreq' => 'hourly']);
        $urls->push(['loc' => self::BASE_URL . '/articles', 'priority' => '0.8', 'changefreq' => 'daily']);

        // Events — most recent 500, sorted newest-start first
        Event::published()
            ->orderByDesc('start_at')
            ->limit(500)
            ->get(['id', 'updated_at'])
            ->each(function ($event) use ($urls) {
                $urls->push([
                    'loc'        => self::BASE_URL . '/events/' . $event->id,
                    'lastmod'    => $event->updated_at?->toAtomString(),
                    'changefreq' => 'weekly',
                    'priority'   => '0.7',
                ]);
            });

        // Posts — root posts only, not hidden, most recent 500
        Post::where('is_hidden', false)
            ->whereNull('parent_id')
            ->orderByDesc('created_at')
            ->limit(500)
            ->get(['id', 'created_at'])
            ->each(function ($post) use ($urls) {
                $urls->push([
                    'loc'        => self::BASE_URL . '/posts/' . $post->id,
                    'lastmod'    => $post->created_at?->toAtomString(),
                    'changefreq' => 'monthly',
                    'priority'   => '0.5',
                ]);
            });

        // Blog articles — all published
        BlogPost::published()
            ->orderByDesc('published_at')
            ->get(['slug', 'published_at', 'updated_at'])
            ->each(function ($article) use ($urls) {
                $urls->push([
                    'loc'        => self::BASE_URL . '/articles/' . $article->slug,
                    'lastmod'    => ($article->updated_at ?? $article->published_at)?->toAtomString(),
                    'changefreq' => 'monthly',
                    'priority'   => '0.8',
                ]);
            });

        return $this->renderXml($urls->all());
    }

    private function renderXml(array $urls): string
    {
        $items = '';
        foreach ($urls as $url) {
            $items .= "\n  <url>";
            $items .= "\n    <loc>" . htmlspecialchars($url['loc'], ENT_XML1) . '</loc>';
            if (!empty($url['lastmod'])) {
                $items .= "\n    <lastmod>" . $url['lastmod'] . '</lastmod>';
            }
            if (!empty($url['changefreq'])) {
                $items .= "\n    <changefreq>" . $url['changefreq'] . '</changefreq>';
            }
            if (!empty($url['priority'])) {
                $items .= "\n    <priority>" . $url['priority'] . '</priority>';
            }
            $items .= "\n  </url>";
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'
            . "\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">"
            . $items
            . "\n</urlset>\n";
    }
}
