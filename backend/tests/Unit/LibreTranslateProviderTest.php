<?php

namespace Tests\Unit;

use App\Services\Translation\Providers\LibreTranslateProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LibreTranslateProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sends_expected_payload_and_parses_response(): void
    {
        config()->set('translation.libretranslate.base_url', 'http://libre.test');
        config()->set('translation.libretranslate.translate_path', '/translate');
        config()->set('translation.libretranslate.internal_token', 'internal-token');

        Http::fake([
            'http://libre.test/*' => Http::response([
                'translatedText' => 'Ahoj svet',
            ], 200),
        ]);

        $result = app(LibreTranslateProvider::class)->translate('Hello world', 'en', 'sk');

        $this->assertSame('Ahoj svet', $result->translatedText);
        $this->assertSame('libretranslate', $result->provider);

        Http::assertSent(function ($request) {
            $data = $request->data();

            return $request->url() === 'http://libre.test/translate'
                && $request->method() === 'POST'
                && data_get($request->headers(), 'X-Internal-Token.0') === 'internal-token'
                && data_get($data, 'q') === 'Hello world'
                && data_get($data, 'source') === 'en'
                && data_get($data, 'target') === 'sk'
                && data_get($data, 'format') === 'text';
        });
    }
}
