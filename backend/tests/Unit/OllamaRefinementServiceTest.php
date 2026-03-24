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
            translatedTitle: 'Maximum meteorickeho roja Perzeidy',
            translatedDescription: 'Rocny meteoricky roj pozorovatelny na tmavej oblohe.'
        );

        $this->assertTrue($result['used_fallback']);
        $this->assertSame('Maximum meteorickeho roja Perzeidy', $result['refined_title']);
        $this->assertSame('Rocny meteoricky roj pozorovatelny na tmavej oblohe.', $result['refined_description']);
    }

    public function test_hallucinated_numeric_date_tokens_fall_back_to_base_translation(): void
    {
        $client = $this->createMock(OllamaClient::class);
        $client->expects($this->once())
            ->method('generate')
            ->willReturn([
                'text' => json_encode([
                    'refined_title' => 'Maximum meteorickeho roja Perzeidy',
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
            translatedTitle: 'Maximum meteorickeho roja Perzeidy',
            translatedDescription: 'Rocny meteoricky roj pozorovatelny na tmavej oblohe.'
        );

        $this->assertTrue($result['used_fallback']);
        $this->assertSame('Maximum meteorickeho roja Perzeidy', $result['refined_title']);
        $this->assertSame('Rocny meteoricky roj pozorovatelny na tmavej oblohe.', $result['refined_description']);
    }

    public function test_decimal_comma_tokens_are_not_rejected_when_input_uses_decimal_dot(): void
    {
        $client = $this->createMock(OllamaClient::class);
        $client->expects($this->once())
            ->method('generate')
            ->willReturn([
                'text' => '{"refined_title":"Jupiter vzdialenos\u0165 3,7 od Mesiaca","refined_description":"Vzdialenos\u0165 je 3,7. Najvhodnej\u0161\u00ed \u010das je 23:01."}',
                'model' => 'mistral',
                'duration_ms' => 10,
                'raw' => [],
            ]);

        $service = new OllamaRefinementService($client);

        $result = $service->refine(
            originalEnglishTitle: 'Jupiter distance 3.7 from Moon',
            originalEnglishDescription: 'Distance is 3.7 and time is 23:01.',
            translatedTitle: 'Jupiter vzdialenost 3,7 od Mesiaca',
            translatedDescription: 'Vzdialenost je 3,7 a cas 23:01.'
        );

        $this->assertFalse($result['used_fallback']);
        $this->assertStringContainsString('3,7', (string) $result['refined_title']);
        $this->assertStringContainsString('23:01', (string) $result['refined_description']);
    }

    public function test_hallucinated_observation_details_fall_back_only_for_description(): void
    {
        $client = $this->createMock(OllamaClient::class);
        $client->expects($this->once())
            ->method('generate')
            ->willReturn([
                'text' => json_encode([
                    'refined_title' => 'Maximalny meteoricky roj',
                    'refined_description' => 'Pozorujte jav dalekohladom z mesta pocas celej noci.',
                ], JSON_UNESCAPED_UNICODE),
                'model' => 'mistral',
                'duration_ms' => 10,
                'raw' => [],
            ]);

        $service = new OllamaRefinementService($client);

        $result = $service->refine(
            originalEnglishTitle: 'Peak of the meteor shower',
            originalEnglishDescription: 'Annual meteor shower visible in dark skies.',
            translatedTitle: 'Maximum meteorickeho roja',
            translatedDescription: 'Rocny meteoricky roj viditelny na tmavej oblohe.'
        );

        $this->assertTrue($result['used_fallback']);
        $this->assertSame('Maximalny meteoricky roj', $result['refined_title']);
        $this->assertSame('Rocny meteoricky roj viditelny na tmavej oblohe.', $result['refined_description']);
    }

    public function test_obvious_anglicism_in_refined_description_falls_back_to_base_translation(): void
    {
        $client = $this->createMock(OllamaClient::class);
        $client->expects($this->once())
            ->method('generate')
            ->willReturn([
                'text' => json_encode([
                    'refined_title' => 'Maximum meteorickeho roja Perzeidy',
                    'refined_description' => 'Pozorovanie sa da conductovat aj volnym okom.',
                ], JSON_UNESCAPED_UNICODE),
                'model' => 'mistral',
                'duration_ms' => 10,
                'raw' => [],
            ]);

        $service = new OllamaRefinementService($client);

        $result = $service->refine(
            originalEnglishTitle: 'Peak of the Perseids meteor shower',
            originalEnglishDescription: 'Annual shower visible in dark skies.',
            translatedTitle: 'Maximum meteorickeho roja Perzeidy',
            translatedDescription: 'Rocny meteoricky roj pozorovatelny na tmavej oblohe.'
        );

        $this->assertTrue($result['used_fallback']);
        $this->assertSame('Maximum meteorickeho roja Perzeidy', $result['refined_title']);
        $this->assertSame('Rocny meteoricky roj pozorovatelny na tmavej oblohe.', $result['refined_description']);
    }
}
