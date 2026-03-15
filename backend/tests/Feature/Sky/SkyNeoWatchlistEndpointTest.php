<?php

namespace Tests\Feature\Sky;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SkyNeoWatchlistEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_a_sorted_neo_watchlist_payload(): void
    {
        Cache::flush();
        config()->set('observing.providers.jpl_sbdd_url', 'https://sbddb.test/query');

        Http::fake(function (Request $request) {
            if (!str_starts_with($request->url(), 'https://sbddb.test/query')) {
                return Http::response(['message' => 'unexpected'], 500);
            }

            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            $this->assertSame('neo', (string) ($query['sb-group'] ?? ''));
            $this->assertSame('-pha,moid', (string) ($query['sort'] ?? ''));

            return Http::response([
                'fields' => ['full_name', 'pdes', 'class', 'neo', 'pha', 'moid', 'diameter', 'H'],
                'data' => [
                    ['433 Eros', '433', 'AMO', 'Y', 'N', '0.148', '16.84', '10.31'],
                    ['99942 Apophis', '99942', 'APO', 'Y', 'Y', '0.00026', '0.37', '19.7'],
                    ['       (2001 FO32)', '2001 FO32', 'APO', 'Y', 'N', '0.0035', '0.97', '17.8'],
                ],
            ], 200);
        });

        $response = $this->getJson('/api/sky/neo-watchlist');

        $response->assertOk()
            ->assertJsonPath('available', true)
            ->assertJsonPath('source.provider', 'jpl_sbddb')
            ->assertJsonPath('source.label', 'NASA JPL SBDB')
            ->assertJsonPath('items.0.name', '99942 Apophis')
            ->assertJsonPath('items.0.pha', true)
            ->assertJsonPath('items.0.orbit_class_label', 'Apollo')
            ->assertJsonPath('items.0.moid_au', 0.00026)
            ->assertJsonPath('items.1.name', '2001 FO32');
    }

    public function test_it_caches_the_neo_watchlist_payload(): void
    {
        Cache::flush();
        config()->set('observing.providers.jpl_sbdd_url', 'https://sbddb.test/query');

        Http::fake([
            'https://sbddb.test/query*' => Http::sequence()
                ->push([
                    'fields' => ['full_name', 'pdes', 'class', 'neo', 'pha', 'moid', 'diameter', 'H'],
                    'data' => [
                        ['99942 Apophis', '99942', 'APO', 'Y', 'Y', '0.00026', '0.37', '19.7'],
                    ],
                ], 200)
                ->push([
                    'fields' => ['full_name', 'pdes', 'class', 'neo', 'pha', 'moid', 'diameter', 'H'],
                    'data' => [
                        ['2001 FO32', '2001 FO32', 'APO', 'Y', 'N', '0.0035', '0.97', '17.8'],
                    ],
                ], 200),
        ]);

        $first = $this->getJson('/api/sky/neo-watchlist')->assertOk();
        $second = $this->getJson('/api/sky/neo-watchlist')->assertOk();

        $this->assertSame($first->json('items.0.name'), $second->json('items.0.name'));
        Http::assertSentCount(1);
    }

    public function test_it_uses_the_local_ca_bundle_for_jpl_requests_in_local_environment(): void
    {
        Cache::flush();
        $this->app['env'] = 'local';
        config()->set('observing.providers.jpl_sbdd_url', 'https://sbddb.test/query');

        $caBundlePath = storage_path('framework/testing/neo-watchlist-cacert.pem');
        File::ensureDirectoryExists(dirname($caBundlePath));
        File::put($caBundlePath, "test-ca-bundle\n");
        config()->set('observing.http.local_ca_bundle_path', $caBundlePath);

        Http::fake([
            'https://sbddb.test/query*' => Http::response([
                'fields' => ['full_name', 'pdes', 'class', 'neo', 'pha', 'moid', 'diameter', 'H'],
                'data' => [
                    ['99942 Apophis', '99942', 'APO', 'Y', 'Y', '0.00026', '0.37', '19.7'],
                ],
            ], 200),
        ]);

        $this->getJson('/api/sky/neo-watchlist')
            ->assertOk()
            ->assertJsonPath('available', true);

        Http::assertSent(fn (Request $request) => $request->url() === 'https://sbddb.test/query?fields=full_name%2Cpdes%2Cclass%2Cneo%2Cpha%2Cmoid%2Cdiameter%2CH&sb-group=neo&sort=-pha%2Cmoid&limit=5'
            && data_get($request->attributes(), 'ssl_verify') === $caBundlePath);
    }

    public function test_it_falls_back_to_the_last_known_watchlist_when_the_provider_is_temporarily_unavailable(): void
    {
        Cache::flush();
        config()->set('observing.providers.jpl_sbdd_url', 'https://sbddb.test/query');

        Http::fake([
            'https://sbddb.test/query*' => Http::sequence()
                ->push([
                    'fields' => ['full_name', 'pdes', 'class', 'neo', 'pha', 'moid', 'diameter', 'H'],
                    'data' => [
                        ['99942 Apophis', '99942', 'APO', 'Y', 'Y', '0.00026', '0.37', '19.7'],
                    ],
                ], 200)
                ->pushStatus(503),
        ]);

        $this->getJson('/api/sky/neo-watchlist')
            ->assertOk()
            ->assertJsonPath('available', true)
            ->assertJsonPath('items.0.name', '99942 Apophis');

        Cache::forget('sky_neo_watchlist:v2');

        $this->getJson('/api/sky/neo-watchlist')
            ->assertOk()
            ->assertJsonPath('available', true)
            ->assertJsonPath('items.0.name', '99942 Apophis')
            ->assertJsonPath('stale', true);

        Http::assertSentCount(2);
    }
}
