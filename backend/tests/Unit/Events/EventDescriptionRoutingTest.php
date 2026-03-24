<?php

namespace Tests\Unit\Events;

use App\Models\Event;
use App\Services\AI\JsonGuard;
use App\Services\AI\OllamaClient;
use App\Services\Events\EventDescriptionGeneratorService;
use App\Services\Events\EventDescriptionTemplateBuilder;
use App\Services\Events\EventInsightsCacheService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Tests\TestCase;

class EventDescriptionRoutingTest extends TestCase
{
    public function test_simple_technical_event_uses_template_even_when_ollama_mode_is_requested(): void
    {
        config()->set('events.ai.description_routing.enabled', true);
        config()->set('events.ai.humanized_pilot_enabled', false);

        $ollamaClient = $this->createMock(OllamaClient::class);
        $ollamaClient
            ->expects($this->never())
            ->method('generate');

        $service = $this->makeService($ollamaClient);
        $event = $this->makeEvent(
            title: 'First Quarter Moon',
            type: 'other',
            sourceName: 'nasa_watch_the_skies'
        );

        $result = $service->generateForEvent($event, 'ollama');

        $this->assertSame('template', (string) ($result['provider'] ?? ''));
        $normalizedDescription = Str::of((string) ($result['description'] ?? ''))->ascii()->lower()->value();
        $this->assertStringContainsString('stvrt', $normalizedDescription);
    }

    public function test_rich_event_keeps_ai_path_when_ollama_mode_is_requested(): void
    {
        config()->set('events.ai.description_routing.enabled', true);
        config()->set('events.ai.humanized_pilot_enabled', false);
        config()->set('events.ai.model', 'mistral');
        config()->set('events.ai.temperature', 0.2);
        config()->set('events.ai.num_predict', 420);
        config()->set('events.ai.timeout', 45);

        $ollamaClient = $this->createMock(OllamaClient::class);
        $ollamaClient
            ->expects($this->once())
            ->method('generate')
            ->willReturn([
                'text' => '{"description":"Artemis II pilotovana misia k Mesiacu je dalsim krokom programu a verejnosti priblizuje navrat ludi k lunarnym letom.","short":"Artemis II je klucovy krok pilotovanej misie k Mesiacu."}',
                'retry_count' => 0,
                'model' => 'mistral',
            ]);

        $service = $this->makeService($ollamaClient);
        $event = $this->makeEvent(
            title: 'Artemis II pilotovana misia k Mesiacu',
            type: 'mission',
            sourceName: 'manual'
        );

        $result = $service->generateForEvent($event, 'ollama');

        $this->assertSame('ollama', (string) ($result['provider'] ?? ''));
        $this->assertStringContainsString('artemis ii', mb_strtolower((string) ($result['description'] ?? '')));
    }

    public function test_meteor_shower_event_defaults_to_template_in_ollama_mode(): void
    {
        config()->set('events.ai.description_routing.enabled', true);
        config()->set('events.ai.humanized_pilot_enabled', false);

        $ollamaClient = $this->createMock(OllamaClient::class);
        $ollamaClient
            ->expects($this->never())
            ->method('generate');

        $service = $this->makeService($ollamaClient);
        $event = $this->makeEvent(
            title: 'Ursids Meteor Shower',
            type: 'meteor_shower',
            sourceName: 'imo'
        );

        $result = $service->generateForEvent($event, 'ollama');

        $this->assertSame('template', (string) ($result['provider'] ?? ''));
        $normalizedDescription = Str::of((string) ($result['description'] ?? ''))->ascii()->lower()->value();
        $this->assertStringContainsString('meteor', $normalizedDescription);
        $this->assertStringNotContainsString('uran', $normalizedDescription);
    }

    private function makeService(OllamaClient $ollamaClient): EventDescriptionGeneratorService
    {
        return new EventDescriptionGeneratorService(
            ollamaClient: $ollamaClient,
            templateBuilder: new EventDescriptionTemplateBuilder(),
            jsonGuard: app(JsonGuard::class),
            insightsCache: app(EventInsightsCacheService::class),
        );
    }

    private function makeEvent(string $title, string $type, string $sourceName): Event
    {
        return new Event([
            'title' => $title,
            'type' => $type,
            'source_name' => $sourceName,
            'source_uid' => 'routing-test-' . uniqid(),
            'region_scope' => 'global',
            'visibility' => 1,
            'start_at' => CarbonImmutable::parse('2026-05-20 20:30:00', 'UTC'),
            'max_at' => CarbonImmutable::parse('2026-05-20 20:30:00', 'UTC'),
            'description' => null,
            'short' => null,
        ]);
    }
}
