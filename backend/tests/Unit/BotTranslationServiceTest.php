<?php

namespace Tests\Unit;

use App\Services\Translation\BotTranslationService;
use App\Services\Translation\Exceptions\TranslationClientException;
use App\Services\Translation\LibreTranslateClient;
use App\Services\Translation\OllamaTranslateClient;
use Tests\TestCase;

class BotTranslationServiceTest extends TestCase
{
    public function test_falls_back_to_ollama_when_libretranslate_fails(): void
    {
        config()->set('astrobot.translation.primary', 'libretranslate');
        config()->set('astrobot.translation.fallback', 'ollama');
        config()->set('astrobot.translation.chunk_max_chars', 1800);
        config()->set('astrobot.translation.chunk_hard_limit_chars', 1800);

        $libre = $this->createMock(LibreTranslateClient::class);
        $libre->expects($this->once())
            ->method('translate')
            ->willThrowException(new TranslationClientException('libretranslate', 'HTTP 500'));

        $ollama = $this->createMock(OllamaTranslateClient::class);
        $ollama->expects($this->once())
            ->method('translate')
            ->willReturn([
                'text' => 'Slovensky titulok',
                'provider' => 'ollama',
                'model' => 'mistral',
                'duration_ms' => 30,
                'chars' => 30,
            ]);

        $service = new BotTranslationService($libre, $ollama);
        $result = $service->translate('English title for fallback', null, 'sk');

        $this->assertSame('done', $result['status']);
        $this->assertSame('Slovensky titulok', $result['translated_title']);
        $this->assertSame('ollama', $result['meta']['provider']);
        $this->assertTrue((bool) $result['meta']['fallback_used']);
        $this->assertSame('sk', $result['meta']['target_lang']);
    }

    public function test_splits_long_text_into_chunks_and_merges_translated_output(): void
    {
        config()->set('astrobot.translation.primary', 'libretranslate');
        config()->set('astrobot.translation.fallback', 'ollama');
        config()->set('astrobot.translation.chunk_max_chars', 60);
        config()->set('astrobot.translation.chunk_hard_limit_chars', 60);

        $paragraphOne = 'First paragraph has enough words for chunking.';
        $paragraphTwo = 'Second paragraph also keeps the chunk flowing.';
        $paragraphThree = 'Third paragraph confirms merge behavior.';
        $input = $paragraphOne . "\n\n" . $paragraphTwo . "\n\n" . $paragraphThree;

        $libre = $this->createMock(LibreTranslateClient::class);
        $captured = [];
        $libre->expects($this->exactly(3))
            ->method('translate')
            ->willReturnCallback(function (string $text) use (&$captured): array {
                $captured[] = $text;

                return [
                    'text' => 'SK<' . $text . '>',
                    'provider' => 'libretranslate',
                    'model' => null,
                    'duration_ms' => 10,
                    'chars' => function_exists('mb_strlen') ? mb_strlen($text) : strlen($text),
                ];
            });

        $ollama = $this->createMock(OllamaTranslateClient::class);
        $ollama->expects($this->never())->method('translate');

        $service = new BotTranslationService($libre, $ollama);
        $result = $service->translate(null, $input, 'sk');

        $this->assertCount(3, $captured);
        $this->assertSame('done', $result['status']);
        $this->assertSame(
            'SK<' . $paragraphOne . ">\n\nSK<" . $paragraphTwo . ">\n\nSK<" . $paragraphThree . '>',
            $result['translated_content']
        );
        $this->assertSame('libretranslate', $result['meta']['provider']);
    }
}

