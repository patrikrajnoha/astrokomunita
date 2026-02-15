<?php

namespace Tests\Unit;

use App\Services\AI\OllamaClient;
use App\Services\AI\OllamaClientException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OllamaClientTest extends TestCase
{
    public function test_it_sends_expected_payload_and_parses_response(): void
    {
        config()->set('ai.ollama.base_url', 'http://ollama.test');
        config()->set('ai.ollama.generate_path', '/api/generate');
        config()->set('ai.ollama.model', 'mistral');
        config()->set('ai.ollama.internal_token', 'internal-token');

        Http::fake([
            'http://ollama.test/*' => Http::response([
                'model' => 'mistral',
                'response' => 'Vystup modelu',
                'done' => true,
            ], 200),
        ]);

        $result = app(OllamaClient::class)->generate('Prompt text', 'System text');

        $this->assertSame('Vystup modelu', $result['text']);
        $this->assertSame('mistral', $result['model']);
        $this->assertIsInt($result['duration_ms']);

        Http::assertSent(function ($request) {
            $data = $request->data();

            return $request->url() === 'http://ollama.test/api/generate'
                && data_get($request->headers(), 'X-Internal-Token.0') === 'internal-token'
                && data_get($data, 'model') === 'mistral'
                && data_get($data, 'prompt') === 'Prompt text'
                && data_get($data, 'system') === 'System text'
                && data_get($data, 'stream') === false;
        });
    }

    public function test_it_throws_domain_exception_on_http_error(): void
    {
        config()->set('ai.ollama.base_url', 'http://ollama.test');

        Http::fake([
            'http://ollama.test/*' => Http::response(['error' => 'down'], 503),
        ]);

        $this->expectException(OllamaClientException::class);
        $this->expectExceptionMessage('Ollama failed with HTTP 503.');

        app(OllamaClient::class)->generate('Prompt');
    }
}
