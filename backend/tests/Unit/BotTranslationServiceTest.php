<?php

namespace Tests\Unit;

use App\Services\Translation\BotTranslationService;
use App\Services\Translation\Exceptions\TranslationClientException;
use App\Services\Translation\LibreTranslateClient;
use App\Services\Translation\OllamaTranslateClient;
use App\Services\Translation\TranslationOutageSimulationService;
use Tests\TestCase;

class BotTranslationServiceTest extends TestCase
{
    public function test_falls_back_to_ollama_when_libretranslate_fails(): void
    {
        config()->set('bots.translation.primary', 'libretranslate');
        config()->set('bots.translation.fallback', 'ollama');
        config()->set('bots.translation.post_edit.enabled', false);
        config()->set('bots.translation.quality.enabled', false);
        config()->set('bots.translation.chunk_max_chars', 1800);
        config()->set('bots.translation.chunk_hard_limit_chars', 1800);

        $libre = $this->createMock(LibreTranslateClient::class);
        $libre->expects($this->once())
            ->method('translate')
            ->willThrowException(new TranslationClientException('libretranslate', 'HTTP 500'));

        $ollama = $this->createMock(OllamaTranslateClient::class);
        $ollama->expects($this->once())
            ->method('translateDirect')
            ->willReturn([
                'text' => 'Slovensky titulok',
                'provider' => 'ollama',
                'model' => 'mistral',
                'duration_ms' => 30,
                'chars' => 30,
            ]);

        $service = $this->makeService($libre, $ollama);
        $result = $service->translate('English title for fallback', null, 'sk');

        $this->assertSame('done', $result['status']);
        $this->assertSame('Slovensky titulok', $result['translated_title']);
        $this->assertSame('ollama', $result['meta']['provider']);
        $this->assertTrue((bool) $result['meta']['fallback_used']);
        $this->assertSame('sk', $result['meta']['target_lang']);
    }

    public function test_splits_long_text_into_chunks_and_merges_translated_output(): void
    {
        config()->set('bots.translation.primary', 'libretranslate');
        config()->set('bots.translation.fallback', 'ollama');
        config()->set('bots.translation.post_edit.enabled', false);
        config()->set('bots.translation.quality.enabled', false);
        config()->set('bots.translation.chunk_max_chars', 60);
        config()->set('bots.translation.chunk_hard_limit_chars', 60);

        $paragraphOne = 'First paragraph has enough words for chunking.';
        $paragraphTwo = 'Second paragraph also keeps the chunk flowing.';
        $paragraphThree = 'Third paragraph confirms merge behavior.';
        $input = $paragraphOne."\n\n".$paragraphTwo."\n\n".$paragraphThree;

        $libre = $this->createMock(LibreTranslateClient::class);
        $captured = [];
        $libre->expects($this->exactly(3))
            ->method('translate')
            ->willReturnCallback(function (string $text) use (&$captured): array {
                $captured[] = $text;

                return [
                    'text' => 'SK<'.$text.'>',
                    'provider' => 'libretranslate',
                    'model' => null,
                    'duration_ms' => 10,
                    'chars' => function_exists('mb_strlen') ? mb_strlen($text) : strlen($text),
                ];
            });

        $ollama = $this->createMock(OllamaTranslateClient::class);
        $ollama->expects($this->never())->method('translateDirect');

        $service = $this->makeService($libre, $ollama);
        $result = $service->translate(null, $input, 'sk');

        $this->assertCount(3, $captured);
        $this->assertSame('done', $result['status']);
        $this->assertSame(
            'SK<'.$paragraphOne.">\n\nSK<".$paragraphTwo.">\n\nSK<".$paragraphThree.'>',
            $result['translated_content']
        );
        $this->assertSame('libretranslate', $result['meta']['provider']);
    }

    public function test_quality_retry_runs_when_translation_is_too_short(): void
    {
        config()->set('bots.translation.primary', 'libretranslate');
        config()->set('bots.translation.fallback', 'ollama');
        config()->set('bots.translation.post_edit.enabled', false);
        config()->set('bots.translation.quality.enabled', true);
        config()->set('bots.translation.quality.max_retries', 1);
        config()->set('bots.translation.quality.min_length_ratio', 0.7);
        config()->set('bots.translation.quality.max_english_ratio', 1.0);

        $libre = $this->createMock(LibreTranslateClient::class);
        $libre->expects($this->once())
            ->method('translate')
            ->willReturn([
                'text' => 'kratke',
                'provider' => 'libretranslate',
                'model' => null,
                'duration_ms' => 10,
                'chars' => 120,
            ]);

        $ollama = $this->createMock(OllamaTranslateClient::class);
        $ollama->expects($this->once())
            ->method('translateDirect')
            ->willReturn([
                'text' => 'Toto je dostatocne dlhy a prirodzenejsi slovensky preklad celeho odstavca.',
                'provider' => 'ollama',
                'model' => 'mistral',
                'duration_ms' => 30,
                'chars' => 120,
            ]);

        $service = $this->makeService($libre, $ollama);
        $result = $service->translate(null, str_repeat('English source text ', 8), 'sk');

        $this->assertSame('done', $result['status']);
        $this->assertSame('ollama', $result['meta']['provider']);
        $this->assertSame(1, (int) $result['meta']['quality_retry_count']);
    }

    public function test_quality_retry_runs_when_translation_is_identical_to_source(): void
    {
        config()->set('bots.translation.primary', 'libretranslate');
        config()->set('bots.translation.fallback', 'ollama');
        config()->set('bots.translation.post_edit.enabled', false);
        config()->set('bots.translation.quality.enabled', true);
        config()->set('bots.translation.quality.max_retries', 1);
        config()->set('bots.translation.quality.min_length_ratio', 0.1);
        config()->set('bots.translation.quality.max_english_ratio', 1.0);

        $source = 'This source sentence should not be returned unchanged.';

        $libre = $this->createMock(LibreTranslateClient::class);
        $libre->expects($this->once())
            ->method('translate')
            ->willReturn([
                'text' => $source,
                'provider' => 'libretranslate',
                'model' => null,
                'duration_ms' => 10,
                'chars' => 64,
            ]);

        $ollama = $this->createMock(OllamaTranslateClient::class);
        $ollama->expects($this->once())
            ->method('translateDirect')
            ->willReturn([
                'text' => 'Tato veta je po slovensky a nie je identicka s originalom.',
                'provider' => 'ollama',
                'model' => 'mistral',
                'duration_ms' => 25,
                'chars' => 64,
            ]);

        $service = $this->makeService($libre, $ollama);
        $result = $service->translate($source, null, 'sk');

        $this->assertSame('done', $result['status']);
        $this->assertSame('Tato veta je po slovensky a nie je identicka s originalom.', $result['translated_title']);
        $this->assertSame(1, (int) $result['meta']['quality_retry_count']);
    }

    public function test_quality_retry_runs_when_slovak_output_contains_english_connectors(): void
    {
        config()->set('bots.translation.primary', 'libretranslate');
        config()->set('bots.translation.fallback', 'ollama');
        config()->set('bots.translation.post_edit.enabled', false);
        config()->set('bots.translation.quality.enabled', true);
        config()->set('bots.translation.quality.max_retries', 1);
        config()->set('bots.translation.quality.min_length_ratio', 0.1);
        config()->set('bots.translation.quality.max_english_ratio', 1.0);

        $libre = $this->createMock(LibreTranslateClient::class);
        $libre->expects($this->once())
            ->method('translate')
            ->willReturn([
                'text' => 'Saturn with Slnko',
                'provider' => 'libretranslate',
                'model' => null,
                'duration_ms' => 10,
                'chars' => 40,
            ]);

        $ollama = $this->createMock(OllamaTranslateClient::class);
        $ollama->expects($this->once())
            ->method('translateDirect')
            ->willReturn([
                'text' => 'Saturn v konjunkcii so Slnkom',
                'provider' => 'ollama',
                'model' => 'mistral',
                'duration_ms' => 25,
                'chars' => 40,
            ]);

        $service = $this->makeService($libre, $ollama);
        $result = $service->translate('Saturn in Conjunction with Sun', null, 'sk');

        $this->assertSame('done', $result['status']);
        $this->assertSame('Saturn v konjunkcii so Slnkom', $result['translated_title']);
        $this->assertSame('ollama', $result['meta']['provider']);
        $this->assertSame(1, (int) $result['meta']['quality_retry_count']);
    }

    public function test_quality_retry_runs_when_translation_has_encoding_artifacts(): void
    {
        config()->set('bots.translation.primary', 'libretranslate');
        config()->set('bots.translation.fallback', 'ollama');
        config()->set('bots.translation.post_edit.enabled', false);
        config()->set('bots.translation.quality.enabled', true);
        config()->set('bots.translation.quality.max_retries', 1);
        config()->set('bots.translation.quality.min_length_ratio', 0.1);
        config()->set('bots.translation.quality.max_english_ratio', 1.0);

        $libre = $this->createMock(LibreTranslateClient::class);
        $libre->expects($this->once())
            ->method('translate')
            ->willReturn([
                'text' => "Venu\u{00C5}\u{00A1}a v Inferior Conjunction",
                'provider' => 'libretranslate',
                'model' => null,
                'duration_ms' => 10,
                'chars' => 42,
            ]);

        $ollama = $this->createMock(OllamaTranslateClient::class);
        $ollama->expects($this->once())
            ->method('translateDirect')
            ->willReturn([
                'text' => "Venu\u{0161}a v dolnej konjunkcii",
                'provider' => 'ollama',
                'model' => 'mistral',
                'duration_ms' => 25,
                'chars' => 42,
            ]);

        $service = $this->makeService($libre, $ollama);
        $result = $service->translate('Venus at Inferior Conjunction', null, 'sk');

        $this->assertSame('done', $result['status']);
        $this->assertSame("Venu\u{0161}a v dolnej konjunkcii", $result['translated_title']);
        $this->assertSame('ollama', $result['meta']['provider']);
        $this->assertSame(1, (int) $result['meta']['quality_retry_count']);
    }
    public function test_uses_ollama_post_edit_after_libretranslate_when_enabled(): void
    {
        config()->set('bots.translation.primary', 'libretranslate');
        config()->set('bots.translation.fallback', 'ollama');
        config()->set('bots.translation.post_edit.enabled', true);
        config()->set('bots.translation.quality.enabled', false);

        $libre = $this->createMock(LibreTranslateClient::class);
        $libre->expects($this->once())
            ->method('translate')
            ->willReturn([
                'text' => 'Hruby preklad',
                'provider' => 'libretranslate',
                'model' => null,
                'duration_ms' => 10,
                'chars' => 30,
            ]);

        $ollama = $this->createMock(OllamaTranslateClient::class);
        $ollama->expects($this->once())
            ->method('postEdit')
            ->willReturn([
                'text' => 'Prirodzeny a spisovny preklad.',
                'provider' => 'ollama',
                'model' => 'mistral',
                'duration_ms' => 20,
                'chars' => 30,
            ]);
        $ollama->expects($this->never())->method('translateDirect');

        $service = $this->makeService($libre, $ollama);
        $result = $service->translate('Raw title', null, 'sk');

        $this->assertSame('Prirodzeny a spisovny preklad.', $result['translated_title']);
        $this->assertSame('ollama_postedit', $result['meta']['provider']);
        $this->assertContains('ollama_postedit', $result['meta']['provider_chain']);
        $this->assertSame('lt_ollama_postedit', $result['meta']['mode']);
    }

    public function test_protected_terms_are_restored_after_translation(): void
    {
        config()->set('bots.translation.primary', 'libretranslate');
        config()->set('bots.translation.fallback', 'dummy');
        config()->set('bots.translation.post_edit.enabled', false);
        config()->set('bots.translation.quality.enabled', false);
        config()->set('bots.translation.protected_terms', ['James Webb Space Telescope']);

        $libre = $this->createMock(LibreTranslateClient::class);
        $libre->expects($this->once())
            ->method('translate')
            ->willReturnCallback(function (string $text): array {
                return [
                    'text' => str_replace('James Webb Space Telescope', 'Vesmirny teleskop Jamesa Webba', $text),
                    'provider' => 'libretranslate',
                    'model' => null,
                    'duration_ms' => 12,
                    'chars' => 80,
                ];
            });

        $ollama = $this->createMock(OllamaTranslateClient::class);
        $ollama->expects($this->never())->method('translateDirect');
        $ollama->expects($this->never())->method('postEdit');

        $service = $this->makeService($libre, $ollama);
        $result = $service->translate('NASA observes with James Webb Space Telescope.', null, 'sk');

        $this->assertStringContainsString('James Webb Space Telescope', (string) $result['translated_title']);
    }

    public function test_protected_terms_restore_when_provider_normalizes_placeholder_to_term_number(): void
    {
        config()->set('bots.translation.primary', 'libretranslate');
        config()->set('bots.translation.fallback', 'dummy');
        config()->set('bots.translation.post_edit.enabled', false);
        config()->set('bots.translation.quality.enabled', false);
        config()->set('bots.translation.protected_terms', ['Science Activation Programme']);

        $libre = $this->createMock(LibreTranslateClient::class);
        $libre->expects($this->once())
            ->method('translate')
            ->willReturn([
                'text' => 'Program TERM 1 podporuje vzdelavanie.',
                'provider' => 'libretranslate',
                'model' => null,
                'duration_ms' => 12,
                'chars' => 80,
            ]);

        $ollama = $this->createMock(OllamaTranslateClient::class);
        $ollama->expects($this->never())->method('translateDirect');
        $ollama->expects($this->never())->method('postEdit');

        $service = $this->makeService($libre, $ollama);
        $result = $service->translate('Program Science Activation Programme supports education.', null, 'sk');

        $this->assertStringNotContainsString('TERM 1', (string) $result['translated_title']);
        $this->assertStringContainsString('Science Activation Programme', (string) $result['translated_title']);
    }

    public function test_does_not_skip_english_nasa_text_as_slovak_heuristic(): void
    {
        config()->set('bots.translation.primary', 'libretranslate');
        config()->set('bots.translation.fallback', 'dummy');
        config()->set('bots.translation.post_edit.enabled', false);
        config()->set('bots.translation.quality.enabled', false);

        $libre = $this->createMock(LibreTranslateClient::class);
        $libre->expects($this->exactly(2))
            ->method('translate')
            ->willReturnCallback(function (string $text): array {
                $isTitle = str_contains($text, 'Meet Regina Senegal');

                return [
                    'text' => $isTitle ? 'NASA skúma nové údaje.' : 'Bezpečnosť a kvalita sú kľúčové pre programy NASA.',
                    'provider' => 'libretranslate',
                    'model' => null,
                    'duration_ms' => 10,
                    'chars' => 90,
                ];
            });

        $ollama = $this->createMock(OllamaTranslateClient::class);
        $ollama->expects($this->never())->method('translateDirect');
        $ollama->expects($this->never())->method('postEdit');

        $service = $this->makeService($libre, $ollama);
        $result = $service->translate(
            'Meet Regina Senegal, Acting Chief of Johnson’s Quality and Flight Equipment Division',
            "Safety and quality management are integral to every program at NASA's Johnson Space Center.",
            'sk'
        );

        $this->assertSame('done', $result['status']);
        $this->assertSame('libretranslate', $result['meta']['provider']);
    }

    public function test_skips_text_that_is_likely_slovak(): void
    {
        config()->set('bots.translation.primary', 'libretranslate');
        config()->set('bots.translation.fallback', 'dummy');
        config()->set('bots.translation.post_edit.enabled', false);
        config()->set('bots.translation.quality.enabled', false);

        $libre = $this->createMock(LibreTranslateClient::class);
        $libre->expects($this->never())->method('translate');

        $ollama = $this->createMock(OllamaTranslateClient::class);
        $ollama->expects($this->never())->method('translateDirect');
        $ollama->expects($this->never())->method('postEdit');

        $service = $this->makeService($libre, $ollama);
        $result = $service->translate(
            'Vesmírna agentúra dnes zverejnila nové údaje o hviezdach.',
            'Táto správa je v slovenčine a obsahuje diakritiku pre správnu detekciu.',
            'sk'
        );

        $this->assertSame('skipped', $result['status']);
        $this->assertSame('already_slovak_heuristic', $result['meta']['reason']);
    }

    private function makeService(LibreTranslateClient $libre, OllamaTranslateClient $ollama): BotTranslationService
    {
        $outageSimulation = $this->createMock(TranslationOutageSimulationService::class);
        $outageSimulation->method('shouldSimulateFor')->willReturn(false);

        return new BotTranslationService($libre, $ollama, $outageSimulation);
    }
}
