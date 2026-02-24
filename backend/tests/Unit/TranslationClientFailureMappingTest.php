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
}
