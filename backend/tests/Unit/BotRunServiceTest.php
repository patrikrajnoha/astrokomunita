<?php

namespace Tests\Unit;

use App\Enums\BotRunStatus;
use App\Enums\BotSourceType;
use App\Models\BotSource;
use App\Services\Bots\BotRunService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BotRunServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_start_and_finish_run_persists_audit_status_and_stats(): void
    {
        $source = BotSource::query()->create([
            'key' => 'wikipedia.daily',
            'bot_identity' => 'kozmo',
            'source_type' => BotSourceType::WIKIPEDIA->value,
            'url' => 'https://example.test/wiki',
            'is_enabled' => true,
            'schedule' => ['hourly' => true],
        ]);

        $service = app(BotRunService::class);

        $run = $service->startRun($source, [
            'run_context' => 'admin',
            'mode' => 'dry',
        ]);

        $this->assertDatabaseHas('bot_runs', [
            'id' => $run->id,
            'source_id' => $source->id,
            'bot_identity' => 'kozmo',
        ]);
        $this->assertNotNull($run->started_at);
        $this->assertNull($run->finished_at);
        $this->assertNull($run->status);
        $this->assertSame('admin', (string) data_get($run->meta, 'run_context'));
        $this->assertSame('dry', (string) data_get($run->meta, 'mode'));

        $stats = [
            'fetched' => 10,
            'new' => 4,
            'dupes' => 6,
            'translated' => 0,
            'published' => 0,
        ];

        $finished = $service->finishRun($run, BotRunStatus::PARTIAL, $stats, 'translation skipped', [
            'publish_limit' => 10,
        ]);

        $this->assertNotNull($finished->finished_at);
        $this->assertSame(BotRunStatus::PARTIAL, $finished->status);
        $this->assertSame($stats, $finished->stats);
        $this->assertSame('translation skipped', $finished->error_text);
        $this->assertSame('admin', (string) data_get($finished->meta, 'run_context'));
        $this->assertSame('dry', (string) data_get($finished->meta, 'mode'));
        $this->assertSame(10, (int) data_get($finished->meta, 'publish_limit'));

        $source->refresh();
        $this->assertNotNull($source->last_run_at);
    }
}
