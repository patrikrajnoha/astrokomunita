<?php

namespace Tests\Feature\Bots;

use App\Enums\BotSourceType;
use App\Models\BotItem;
use App\Models\BotSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WikipediaWikidataClassificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config()->set('moderation.enabled', false);
        config()->set('astrobot.translation_provider', 'dummy');
        config()->set('astrobot.wikipedia_mediawiki_api_url', 'https://mediawiki.test/w/api.php');
        config()->set('astrobot.wikidata_api_url', 'https://wikidata.test/w/api.php');
        config()->set('astrobot.wiki_max_candidate_pages', 15);
        config()->set('astrobot.wiki_max_wikidata_entity_requests', 15);
        config()->set('astrobot.wiki_wikidata_cache_ttl_days', 30);
        config()->set('astrobot.wiki_high_keyword_threshold', 4);
    }

    public function test_keyword_hit_with_allowlist_type_publishes_post(): void
    {
        $source = $this->createWikipediaSource();

        Http::fake([
            $this->wikiEndpointPattern($source->url) => Http::response($this->onThisDayPayload('Voyager space mission reaches Jupiter.'), 200),
            'https://mediawiki.test/w/api.php*' => Http::response($this->mediaWikiPagePropsResponse('Voyager_program', 'QITEM_ALLOW'), 200),
            'https://wikidata.test/w/api.php*' => Http::response($this->wikidataEntitiesResponse([
                'QITEM_ALLOW' => $this->entityWithClaims(['P31' => ['Q2133344']]),
            ]), 200),
        ]);

        $exitCode = Artisan::call('bots:run', ['sourceKey' => $source->key]);

        $this->assertSame(0, $exitCode);
        $this->assertDatabaseCount('posts', 1);

        $item = BotItem::query()->firstOrFail();
        $this->assertSame('published', (string) $item->publish_status->value);
    }

    public function test_keyword_hit_with_denylist_type_skips_publish(): void
    {
        $source = $this->createWikipediaSource();

        Http::fake([
            $this->wikiEndpointPattern($source->url) => Http::response($this->onThisDayPayload('Space movie premiere with galaxy theme.'), 200),
            'https://mediawiki.test/w/api.php*' => Http::response($this->mediaWikiPagePropsResponse('Space_movie', 'QITEM_DENY'), 200),
            'https://wikidata.test/w/api.php*' => Http::response($this->wikidataEntitiesResponse([
                'QITEM_DENY' => $this->entityWithClaims(['P31' => ['Q11424']]),
            ]), 200),
        ]);

        $exitCode = Artisan::call('bots:run', ['sourceKey' => $source->key]);

        $this->assertSame(0, $exitCode);
        $this->assertDatabaseCount('posts', 0);

        $item = BotItem::query()->firstOrFail();
        $this->assertSame('skipped', (string) $item->publish_status->value);
        $this->assertSame('no_relevant_events', (string) data_get($item->meta, 'skip_reason'));
    }

    public function test_subclass_chain_allows_publish_within_depth_limit(): void
    {
        $source = $this->createWikipediaSource();

        Http::fake([
            $this->wikiEndpointPattern($source->url) => Http::response($this->onThisDayPayload('Historic probe mission launch.'), 200),
            'https://mediawiki.test/w/api.php*' => Http::response($this->mediaWikiPagePropsResponse('Historic_probe', 'QITEM_SUB'), 200),
            'https://wikidata.test/w/api.php*' => function ($request) {
                $query = [];
                parse_str((string) parse_url((string) $request->url(), PHP_URL_QUERY), $query);
                $ids = strtoupper((string) ($query['ids'] ?? ''));

                if (str_contains($ids, 'QITEM_SUB')) {
                    return Http::response($this->wikidataEntitiesResponse([
                        'QITEM_SUB' => $this->entityWithClaims(['P31' => ['QNODE_ONE']]),
                    ]), 200);
                }

                if (str_contains($ids, 'QNODE_ONE')) {
                    return Http::response($this->wikidataEntitiesResponse([
                        'QNODE_ONE' => $this->entityWithClaims(['P279' => ['QNODE_TWO']]),
                    ]), 200);
                }

                return Http::response($this->wikidataEntitiesResponse([
                    'QNODE_TWO' => $this->entityWithClaims(['P279' => ['Q2133344']]),
                ]), 200);
            },
        ]);

        $exitCode = Artisan::call('bots:run', ['sourceKey' => $source->key]);

        $this->assertSame(0, $exitCode);
        $this->assertDatabaseCount('posts', 1);
    }

    public function test_wikidata_timeout_with_high_keyword_uses_failed_fallback_publish(): void
    {
        $source = $this->createWikipediaSource();

        Http::fake([
            $this->wikiEndpointPattern($source->url) => Http::response($this->onThisDayPayload('NASA space mission spacecraft orbit galaxy supernova telescope.'), 200),
            'https://mediawiki.test/w/api.php*' => Http::response($this->mediaWikiPagePropsResponse('Fallback_page', 'QITEM_FAIL'), 200),
            'https://wikidata.test/w/api.php*' => Http::failedConnection(),
        ]);

        $exitCode = Artisan::call('bots:run', ['sourceKey' => $source->key]);

        $this->assertSame(0, $exitCode);
        $this->assertDatabaseCount('posts', 1);

        $item = BotItem::query()->firstOrFail();
        $this->assertSame('failed_fallback', (string) data_get($item->meta, 'wikidata_status'));
    }

    public function test_cached_wikidata_lookup_avoids_second_run_requests(): void
    {
        $source = $this->createWikipediaSource();
        $mediaWikiRequests = 0;
        $wikidataRequests = 0;

        Http::fake([
            $this->wikiEndpointPattern($source->url) => Http::response($this->onThisDayPayload('Voyager mission reaches distant orbit.'), 200),
            'https://mediawiki.test/w/api.php*' => function () use (&$mediaWikiRequests) {
                $mediaWikiRequests++;
                return Http::response($this->mediaWikiPagePropsResponse('Voyager_program', 'QITEM_CACHE'), 200);
            },
            'https://wikidata.test/w/api.php*' => function () use (&$wikidataRequests) {
                $wikidataRequests++;
                return Http::response($this->wikidataEntitiesResponse([
                    'QITEM_CACHE' => $this->entityWithClaims(['P31' => ['Q2133344']]),
                ]), 200);
            },
        ]);

        $firstExitCode = Artisan::call('bots:run', ['sourceKey' => $source->key]);
        $secondExitCode = Artisan::call('bots:run', ['sourceKey' => $source->key]);

        $this->assertSame(0, $firstExitCode);
        $this->assertSame(0, $secondExitCode);
        $this->assertSame(1, $mediaWikiRequests);
        $this->assertSame(1, $wikidataRequests);
    }

    private function createWikipediaSource(): BotSource
    {
        return BotSource::query()->create([
            'key' => 'wiki_onthisday_astronomy',
            'bot_identity' => 'kozmo',
            'source_type' => BotSourceType::WIKIPEDIA->value,
            'url' => 'https://example.test/wiki/onthisday/all',
            'is_enabled' => true,
            'schedule' => null,
        ]);
    }

    private function wikiEndpointPattern(string $baseUrl): string
    {
        return rtrim($baseUrl, '/') . '/*';
    }

    /**
     * @return array<string,mixed>
     */
    private function onThisDayPayload(string $eventText): array
    {
        return [
            'events' => [[
                'year' => 1986,
                'text' => $eventText,
                'pages' => [[
                    'title' => 'Voyager_program',
                    'content_urls' => [
                        'desktop' => [
                            'page' => 'https://en.wikipedia.org/wiki/Voyager_program',
                        ],
                    ],
                ]],
            ]],
            'births' => [],
            'deaths' => [],
            'holidays' => [],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function mediaWikiPagePropsResponse(string $title, string $wikibaseItem): array
    {
        return [
            'query' => [
                'pages' => [[
                    'pageid' => 123,
                    'title' => $title,
                    'pageprops' => [
                        'wikibase_item' => $wikibaseItem,
                    ],
                ]],
            ],
        ];
    }

    /**
     * @param array<string,array<string,mixed>> $entities
     * @return array<string,mixed>
     */
    private function wikidataEntitiesResponse(array $entities): array
    {
        return [
            'entities' => $entities,
        ];
    }

    /**
     * @param array<string,array<int,string>> $claimsByProperty
     * @return array<string,mixed>
     */
    private function entityWithClaims(array $claimsByProperty): array
    {
        $claims = [];
        foreach ($claimsByProperty as $property => $qids) {
            $claims[$property] = array_map(static fn (string $qid): array => [
                'mainsnak' => [
                    'datavalue' => [
                        'value' => [
                            'id' => $qid,
                        ],
                    ],
                ],
            ], $qids);
        }

        return [
            'claims' => $claims,
        ];
    }
}
