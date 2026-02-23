<?php

namespace Tests\Unit;

use App\Services\AI\OllamaClient;
use App\Services\Bots\OllamaBotTranslationService;
use Tests\TestCase;

class OllamaBotTranslationServiceTest extends TestCase
{
    public function test_translates_title_and_content_with_ollama(): void
    {
        $client = $this->createMock(OllamaClient::class);
        $client->expects($this->exactly(2))
            ->method('generate')
            ->willReturnOnConsecutiveCalls(
                [
                    'text' => 'Nadpis po slovensky',
                    'model' => 'mistral:latest',
                    'duration_ms' => 15,
                    'raw' => [],
                ],
                [
                    'text' => 'Obsah po slovensky',
                    'model' => 'mistral:latest',
                    'duration_ms' => 17,
                    'raw' => [],
                ]
            );

        $service = new OllamaBotTranslationService($client);
        $result = $service->translate('English title', 'English content', 'sk');

        $this->assertSame('done', $result['status']);
        $this->assertSame('Nadpis po slovensky', $result['translated_title']);
        $this->assertSame('Obsah po slovensky', $result['translated_content']);
        $this->assertSame('ollama', $result['meta']['provider']);
    }

    public function test_returns_skipped_for_empty_input(): void
    {
        $client = $this->createMock(OllamaClient::class);
        $client->expects($this->never())->method('generate');

        $service = new OllamaBotTranslationService($client);
        $result = $service->translate(' ', null, 'sk');

        $this->assertSame('skipped', $result['status']);
        $this->assertNull($result['translated_title']);
        $this->assertNull($result['translated_content']);
    }
}

