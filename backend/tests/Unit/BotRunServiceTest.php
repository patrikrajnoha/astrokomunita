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
        $this->assertSame(BotRunStatus::RUNNING, $run->status);
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

    public function test_recover_stale_runs_marks_only_runs_older_than_threshold(): void
    {
        $source = BotSource::query()->create([
            'key' => 'wiki-stale-test',
            'bot_identity' => 'kozmo',
            'source_type' => BotSourceType::WIKIPEDIA->value,
            'url' => 'https://example.test/wiki',
            'is_enabled' => true,
            'schedule' => ['hourly' => true],
        ]);

        $service = app(BotRunService::class);
        $recoveryOwnerRun = $service->startRun($source, ['run_context' => 'admin']);

        $oldRun = $service->startRun($source, ['run_context' => 'admin']);
        $youngRun = $service->startRun($source, ['run_context' => 'admin']);

        \App\Models\BotRun::query()->whereKey($oldRun->id)->update([
            'created_at' => now()->subMinutes(10),
            'updated_at' => now()->subMinutes(10),
        ]);
        \App\Models\BotRun::query()->whereKey($youngRun->id)->update([
            'created_at' => now()->subMinutes(2),
            'updated_at' => now()->subMinutes(2),
        ]);

        $recoveredCount = $service->recoverStaleRunsForSource($source, $recoveryOwnerRun->id, 5);
        $this->assertSame(1, $recoveredCount);

        $oldRun->refresh();
        $youngRun->refresh();

        $this->assertSame(BotRunStatus::FAILED, $oldRun->status);
        $this->assertNotNull($oldRun->finished_at);
        $this->assertSame('stale_run_recovered', (string) data_get($oldRun->meta, 'failure_reason'));
        $this->assertSame($recoveryOwnerRun->id, (int) data_get($oldRun->meta, 'recovered_by_run_id'));

        $this->assertSame(BotRunStatus::RUNNING, $youngRun->status);
        $this->assertNull($youngRun->finished_at);
    }
}
