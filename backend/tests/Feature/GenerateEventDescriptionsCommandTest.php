<?php

namespace Tests\Feature;

use App\Models\Event;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GenerateEventDescriptionsCommandTest extends TestCase
{
    use RefreshDatabase;

    private function createEvent(string $title = 'prvá štvrť Mesiaca'): Event
    {
        return Event::query()->create([
            'title' => $title,
            'type' => 'other',
            'start_at' => CarbonImmutable::parse('2026-02-24 12:28:00', 'UTC'),
            'max_at' => CarbonImmutable::parse('2026-02-24 12:28:00', 'UTC'),
            'short' => null,
            'description' => null,
            'visibility' => 1,
            'source_name' => 'astropixels',
            'source_uid' => 'evt-desc-1',
            'source_hash' => hash('sha256', 'evt-desc-1'),
        ]);
    }

    public function test_command_generates_template_descriptions_and_updates_events(): void
    {
        config()->set('events.ai.description_mode', 'template');

        $event = $this->createEvent();

        $this->artisan('events:generate-descriptions --force --limit=1 --mode=template')
            ->assertExitCode(0);

        $event->refresh();

        $this->assertNotNull($event->description);
        $this->assertNotNull($event->short);
        $this->assertStringContainsString('štvrť', (string) $event->description);
        $this->assertStringContainsString('24. 02. 2026', (string) $event->description);
    }

    public function test_command_ollama_mode_generates_descriptions(): void
    {
        config()->set('ai.ollama.base_url', 'http://ollama.test');
        config()->set('ai.ollama.generate_path', '/api/generate');
        config()->set('events.ai.model', 'mistral');

        Http::fake([
            'http://ollama.test/*' => Http::response([
                'model' => 'mistral',
                'response' => '{"description":"Prva stvrt Mesiaca je vhodna na vecerne pozorovanie terminatora.","short":"Prva stvrt Mesiaca pre vecerne pozorovanie."}',
                'done' => true,
            ], 200),
        ]);

        $event = $this->createEvent('First Quarter Moon');

        $this->artisan('events:generate-descriptions --force --limit=1 --mode=ollama')
            ->assertExitCode(0);

        $event->refresh();
        $this->assertNotNull($event->description);
        $this->assertStringContainsString('terminatora', (string) $event->description);
    }

    public function test_command_dry_run_does_not_persist_changes(): void
    {
        $event = $this->createEvent();

        $this->artisan('events:generate-descriptions --force --dry-run --limit=1 --mode=template')
            ->assertExitCode(0);

        $event->refresh();
        $this->assertNull($event->description);
        $this->assertNull($event->short);
    }
}
