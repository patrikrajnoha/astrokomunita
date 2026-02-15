<?php

namespace Tests\Unit\Support\Http;

use App\Services\AI\OllamaClient;
use App\Services\AstroBotNasaService;
use App\Services\AstroBotPublisher;
use App\Services\Crawlers\Astropixels\AstropixelsAlmanacParser;
use App\Services\Crawlers\AstropixelsCrawlerService;
use App\Services\Crawlers\CrawlContext;
use App\Services\Translation\Grammar\LanguageToolGrammarChecker;
use App\Services\Translation\Providers\LibreTranslateProvider;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class HttpClientSslEnforcementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->app['env'] = 'production';
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_astrobot_forces_ssl_verification_in_production_even_when_disabled_in_config(): void
    {
        config()->set('astrobot.nasa_rss_url', 'https://example.test/feed.xml');
        config()->set('astrobot.ssl_verify', false);
        config()->set('astrobot.ssl_ca_bundle', null);

        Http::fake([
            'https://example.test/*' => Http::response($this->rssPayload(), 200),
        ]);

        $service = new AstroBotNasaService(Mockery::mock(AstroBotPublisher::class));
        $items = $service->fetchFeed();

        $this->assertIsArray($items);
        Http::assertSent(function ($request) {
            $sslVerify = data_get($request->attributes(), 'ssl_verify');

            return $request->url() === 'https://example.test/feed.xml'
                && $sslVerify !== false;
        });
    }

    public function test_astropixels_crawler_forces_ssl_verification_in_production_even_when_disabled_in_config(): void
    {
        config()->set('events.astropixels.base_url_pattern', 'https://example.test/almanac%dcet.html');
        config()->set('events.crawler_ssl_verify', false);
        config()->set('events.crawler_ssl_ca_bundle', '');

        Http::fake([
            'https://example.test/*' => Http::response(
                File::get(base_path('tests/Fixtures/astropixels/almanac2026cet.html')),
                200
            ),
        ]);

        $service = new AstropixelsCrawlerService(new AstropixelsAlmanacParser());
        $batch = $service->fetchCandidates(new CrawlContext(2026));

        $this->assertGreaterThan(0, count($batch->items));
        Http::assertSent(function ($request) {
            return $request->url() === 'https://example.test/almanac2026cet.html'
                && data_get($request->attributes(), 'ssl_verify') === true;
        });
    }

    public function test_ollama_client_forces_ssl_verification_in_production_even_when_disabled_in_config(): void
    {
        config()->set('ai.ollama.base_url', 'http://ollama.test');
        config()->set('ai.ollama.generate_path', '/api/generate');
        config()->set('ai.ollama.verify_ssl', false);

        Http::fake([
            'http://ollama.test/*' => Http::response([
                'model' => 'mistral',
                'response' => 'ok',
            ], 200),
        ]);

        app(OllamaClient::class)->generate('Prompt text');

        Http::assertSent(function ($request) {
            return $request->url() === 'http://ollama.test/api/generate'
                && data_get($request->attributes(), 'ssl_verify') === true;
        });
    }

    public function test_libretranslate_client_forces_ssl_verification_in_production_even_when_disabled_in_config(): void
    {
        config()->set('translation.libretranslate.base_url', 'http://libre.test');
        config()->set('translation.libretranslate.translate_path', '/translate');
        config()->set('translation.libretranslate.verify_ssl', false);

        Http::fake([
            'http://libre.test/*' => Http::response([
                'translatedText' => 'Ahoj',
            ], 200),
        ]);

        app(LibreTranslateProvider::class)->translate('Hello', 'en', 'sk');

        Http::assertSent(function ($request) {
            return $request->url() === 'http://libre.test/translate'
                && data_get($request->attributes(), 'ssl_verify') === true;
        });
    }

    public function test_languagetool_client_forces_ssl_verification_in_production_even_when_disabled_in_config(): void
    {
        config()->set('translation.grammar.languagetool.base_url', 'http://lt.test');
        config()->set('translation.grammar.languagetool.check_path', '/v2/check');
        config()->set('translation.grammar.languagetool.verify_ssl', false);

        Http::fake([
            'http://lt.test/*' => Http::response([
                'matches' => [],
            ], 200),
        ]);

        app(LanguageToolGrammarChecker::class)->correct('Ahoj svet', 'sk-SK');

        Http::assertSent(function ($request) {
            return $request->url() === 'http://lt.test/v2/check'
                && data_get($request->attributes(), 'ssl_verify') === true;
        });
    }

    private function rssPayload(): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8" ?>
<rss version="2.0">
  <channel>
    <title>NASA Test Feed</title>
    <item>
      <guid>guid-1</guid>
      <title>NASA Alpha</title>
      <link>https://www.nasa.gov/news/a</link>
      <description>Alpha</description>
      <pubDate>Mon, 09 Feb 2026 12:00:00 GMT</pubDate>
    </item>
  </channel>
</rss>
XML;
    }
}
