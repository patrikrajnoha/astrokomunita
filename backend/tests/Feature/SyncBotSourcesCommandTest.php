<?php

namespace Tests\Feature;

use App\Models\BotSchedule;
use App\Models\BotSource;
use App\Models\User;
use App\Services\Bots\BotIdentityUserSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncBotSourcesCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('moderation.enabled', false);
    }

    public function test_it_syncs_default_bot_sources_and_schedules_idempotently(): void
    {
        BotSchedule::query()->delete();
        BotSource::query()->delete();

        $this->assertSame(0, BotSource::query()->count());
        $this->assertSame(0, BotSchedule::query()->count());

        $this->artisan('bots:sources:sync')
            ->expectsOutputToContain('Bot automation synchronized.')
            ->assertExitCode(0);

        $this->assertSame(3, BotSource::query()->count());
        $this->assertSame(3, BotSchedule::query()->count());
        $this->assertDatabaseHas('users', [
            'username' => 'kozmobot',
            'is_bot' => 1,
            'role' => User::ROLE_BOT,
        ]);
        $this->assertDatabaseHas('users', [
            'username' => 'stellarbot',
            'is_bot' => 1,
            'role' => User::ROLE_BOT,
        ]);

        $expectedSchedules = [
            'nasa_apod_daily:stellarbot:1440:900',
            'nasa_rss_breaking:kozmobot:60:300',
            'wiki_onthisday_astronomy:kozmobot:1440:900',
        ];
        $this->assertSame($expectedSchedules, $this->scheduleSnapshot());

        $this->artisan('bots:sources:sync')
            ->assertExitCode(0);

        $this->assertSame(3, BotSource::query()->count());
        $this->assertSame(3, BotSchedule::query()->count());
        $this->assertSame($expectedSchedules, $this->scheduleSnapshot());
    }

    public function test_it_skips_source_specific_defaults_when_a_catch_all_schedule_already_exists(): void
    {
        $this->artisan('bots:sources:sync', ['--quiet-summary' => true])->assertExitCode(0);
        BotSchedule::query()->delete();

        $kozmo = app(BotIdentityUserSyncService::class)->ensureBotUser('kozmo');
        BotSchedule::query()->create([
            'bot_user_id' => $kozmo->id,
            'source_id' => null,
            'enabled' => true,
            'interval_minutes' => 30,
            'jitter_seconds' => 0,
            'timezone' => null,
            'next_run_at' => now(),
        ]);

        $this->artisan('bots:sources:sync', ['--quiet-summary' => true])->assertExitCode(0);

        $nasaRss = BotSource::query()->where('key', 'nasa_rss_breaking')->firstOrFail();
        $apod = BotSource::query()->where('key', 'nasa_apod_daily')->firstOrFail();
        $wiki = BotSource::query()->where('key', 'wiki_onthisday_astronomy')->firstOrFail();

        $this->assertSame(2, BotSchedule::query()->count());
        $this->assertDatabaseHas('bot_schedules', [
            'bot_user_id' => $kozmo->id,
            'source_id' => null,
        ]);
        $this->assertDatabaseMissing('bot_schedules', [
            'source_id' => $nasaRss->id,
        ]);
        $this->assertDatabaseMissing('bot_schedules', [
            'source_id' => $wiki->id,
        ]);
        $this->assertDatabaseHas('bot_schedules', [
            'source_id' => $apod->id,
        ]);
    }

    /**
     * @return array<int,string>
     */
    private function scheduleSnapshot(): array
    {
        return BotSchedule::query()
            ->with(['botUser:id,username', 'source:id,key'])
            ->orderBy('id')
            ->get()
            ->map(fn (BotSchedule $schedule): string => sprintf(
                '%s:%s:%d:%d',
                (string) $schedule->source?->key,
                (string) $schedule->botUser?->username,
                (int) $schedule->interval_minutes,
                (int) $schedule->jitter_seconds,
            ))
            ->sort()
            ->values()
            ->all();
    }
}
