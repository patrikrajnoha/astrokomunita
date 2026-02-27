<?php

namespace Tests\Unit;

use App\Services\AI\OllamaClient;
use App\Services\AI\OllamaClientException;
use App\Services\Translation\Exceptions\TranslationProviderUnavailableException;
use App\Services\Translation\Exceptions\TranslationTimeoutException;
use App\Services\Translation\LibreTranslateClient;
use App\Services\Translation\OllamaTranslateClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TranslationClientFailureMappingTest extends TestCase
{
    public function test_libretranslate_client_maps_connection_failure_to_provider_unavailable_exception(): void
    {
        config()->set('astrobot.translation.libretranslate.url', 'http://libretranslate.test');
        config()->set('astrobot.translation.timeout_sec', 5);

        Http::fake([
            'http://libretranslate.test/*' => static function () {
                throw new ConnectionException('cURL error 7: Failed to connect to host');
            },
        ]);

        $client = app(LibreTranslateClient::class);

        $this->expectException(TranslationProviderUnavailableException::class);
        $client->translate('hello', 'sk', 'en');
    }

    public function test_ollama_translate_client_maps_ollama_timeout_error_to_timeout_exception(): void
    {
        $ollamaClient = $this->createMock(OllamaClient::class);
        $ollamaClient
            ->expects($this->once())
            ->method('generate')
            ->willThrowException(new OllamaClientException('timeout', 'ollama_timeout_error'));

        $client = new OllamaTranslateClient($ollamaClient);

        $this->expectException(TranslationTimeoutException::class);
        $client->translate('hello', 'sk', 'en');
    }

    public function test_ollama_translate_client_maps_generic_ollama_failure_to_provider_unavailable_exception(): void
    {
        $ollamaClient = $this->createMock(OllamaClient::class);
        $ollamaClient
            ->expects($this->once())
            ->method('generate')
            ->willThrowException(new OllamaClientException('connection failed', 'ollama_connection_error'));

        $client = new OllamaTranslateClient($ollamaClient);

        $this->expectException(TranslationProviderUnavailableException::class);
        $client->translate('hello', 'sk', 'en');
    }

    public function test_ollama_translate_client_uses_shared_timeout_and_translation_transport_options(): void
    {
        config()->set('astrobot.translation.timeout_sec', 8);
        config()->set('astrobot.translation.ollama.timeout_seconds', 45);
        config()->set('astrobot.translation.connect_timeout_sec', 2);
        config()->set('astrobot.translation.max_retries', 0);

        $ollamaClient = $this->createMock(OllamaClient::class);
        $ollamaClient
            ->expects($this->once())
            ->method('generate')
            ->with(
                $this->isType('string'),
                $this->isType('string'),
                $this->callback(function (array $options): bool {
                    return (int) ($options['timeout'] ?? 0) === 8
                        && (int) ($options['connect_timeout'] ?? 0) === 2
                        && (int) ($options['retry'] ?? -1) === 0;
                })
            )
            ->willReturn([
                'text' => 'Ahoj',
                'model' => 'mistral',
                'duration_ms' => 12,
                'raw' => [],
            ]);

        $client = new OllamaTranslateClient($ollamaClient);
        $result = $client->translate('hello', 'sk', 'en');

        $this->assertSame('Ahoj', $result['text']);
    }
}
