<?php

namespace Tests\Feature;

use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GenerateEventDescriptionsCommandTest extends TestCase
{
    use RefreshDatabase;

    private function createEvent(): Event
    {
        return Event::query()->create([
            'title' => 'First Quarter Moon',
            'type' => 'other',
            'start_at' => now(),
            'max_at' => now(),
            'short' => null,
            'description' => null,
            'visibility' => 1,
            'source_name' => 'astropixels',
            'source_uid' => 'evt-llm-1',
            'source_hash' => hash('sha256', 'evt-llm-1'),
        ]);
    }

    public function test_command_generates_descriptions_and_updates_events(): void
    {
        config()->set('ai.ollama.base_url', 'http://ollama.test');
        config()->set('ai.ollama.generate_path', '/api/generate');
        config()->set('events.ai.model', 'mistral');

        Http::fake([
            'http://ollama.test/*' => Http::response([
                'model' => 'mistral',
                'response' => '{"description":"Táto fáza Mesiaca je vhodná na večerné pozorovanie detailov terminátora.","short":"Fáza prvá štvrť Mesiaca pre večerné pozorovanie."}',
                'done' => true,
            ], 200),
        ]);

        $event = $this->createEvent();

        $this->artisan('events:generate-descriptions --force --limit=1')
            ->assertExitCode(0);

        $event->refresh();
        $this->assertNotNull($event->description);
        $this->assertNotNull($event->short);
        $this->assertStringContainsString('terminátora', (string) $event->description);
    }

    public function test_command_dry_run_does_not_persist_changes(): void
    {
        config()->set('ai.ollama.base_url', 'http://ollama.test');
        config()->set('ai.ollama.generate_path', '/api/generate');

        Http::fake([
            'http://ollama.test/*' => Http::response([
                'model' => 'mistral',
                'response' => '{"description":"Test opis udalosti.","short":"Test krátkeho opisu."}',
                'done' => true,
            ], 200),
        ]);

        $event = $this->createEvent();

        $this->artisan('events:generate-descriptions --force --dry-run --limit=1')
            ->assertExitCode(0);

        $event->refresh();
        $this->assertNull($event->description);
        $this->assertNull($event->short);
    }
}
