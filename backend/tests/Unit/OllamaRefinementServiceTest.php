<?php

namespace Tests\Unit;

use App\Services\AI\OllamaClient;
use App\Services\AI\OllamaRefinementService;
use Tests\TestCase;

class OllamaRefinementServiceTest extends TestCase
{
    public function test_ascii_only_refined_output_falls_back_to_base_translation(): void
    {
        $client = $this->createMock(OllamaClient::class);
        $client->expects($this->once())
            ->method('generate')
            ->willReturn([
                'text' => json_encode([
                    'refined_title' => 'Maximum meteorickeho roja Perzeidy',
                    'refined_description' => 'Ide o periodicky meteoricky roj. Pozorovanie je mozne volnym okom.',
                ], JSON_UNESCAPED_UNICODE),
                'model' => 'mistral',
                'duration_ms' => 10,
                'raw' => [],
            ]);

        $service = new OllamaRefinementService($client);

        $result = $service->refine(
            originalEnglishTitle: 'Peak of the Perseids meteor shower',
            originalEnglishDescription: 'Annual shower visible in dark skies.',
            translatedTitle: 'Maximum meteorického roja Perzeidy',
            translatedDescription: 'Ročný meteorický roj pozorovateľný na tmavej oblohe.'
        );

        $this->assertTrue($result['used_fallback']);
        $this->assertSame('Maximum meteorického roja Perzeidy', $result['refined_title']);
        $this->assertSame('Ročný meteorický roj pozorovateľný na tmavej oblohe.', $result['refined_description']);
    }

    public function test_hallucinated_numeric_date_tokens_fall_back_to_base_translation(): void
    {
        $client = $this->createMock(OllamaClient::class);
        $client->expects($this->once())
            ->method('generate')
            ->willReturn([
                'text' => json_encode([
                    'refined_title' => 'Maximum meteorického roja Perzeidy',
                    'refined_description' => 'Maximum nastane 12.08.2026 o 23:00 v Bratislave.',
                ], JSON_UNESCAPED_UNICODE),
                'model' => 'mistral',
                'duration_ms' => 10,
                'raw' => [],
            ]);

        $service = new OllamaRefinementService($client);

        $result = $service->refine(
            originalEnglishTitle: 'Peak of the Perseids meteor shower',
            originalEnglishDescription: 'Annual shower visible in dark skies.',
            translatedTitle: 'Maximum meteorického roja Perzeidy',
            translatedDescription: 'Ročný meteorický roj pozorovateľný na tmavej oblohe.'
        );

        $this->assertTrue($result['used_fallback']);
        $this->assertSame('Maximum meteorického roja Perzeidy', $result['refined_title']);
        $this->assertSame('Ročný meteorický roj pozorovateľný na tmavej oblohe.', $result['refined_description']);
    }
}
