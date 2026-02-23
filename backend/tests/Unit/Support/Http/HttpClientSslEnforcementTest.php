<?php

namespace Tests\Unit\Support\Http;

use App\Services\AI\OllamaClient;
use App\Services\Crawlers\Astropixels\AstropixelsAlmanacParser;
use App\Services\Crawlers\AstropixelsCrawlerService;
use App\Services\Crawlers\CrawlContext;
use App\Services\Translation\Grammar\LanguageToolGrammarChecker;
use App\Services\Translation\Providers\LibreTranslateProvider;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HttpClientSslEnforcementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->app['env'] = 'production';
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

}
