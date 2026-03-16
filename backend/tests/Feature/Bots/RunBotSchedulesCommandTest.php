<?php

namespace Tests\Feature\Bots;

use App\Enums\BotSourceType;
use App\Models\BotSchedule;
use App\Models\BotSource;
use App\Models\BotRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RunBotSchedulesCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('moderation.enabled', false);
    }

    public function test_schedule_command_runs_due_schedules_and_updates_next_run_at(): void
    {
        $botUser = $this->createBotUser('kozmobot');
        $source = $this->createSource('schedule_due_source');

        $dueSchedule = BotSchedule::query()->create([
            'bot_user_id' => $botUser->id,
            'source_id' => $source->id,
            'enabled' => true,
            'interval_minutes' => 15,
            'jitter_seconds' => 0,
            'next_run_at' => now()->subMinute(),
        ]);
        $futureSchedule = BotSchedule::query()->create([
            'bot_user_id' => $botUser->id,
            'source_id' => $source->id,
            'enabled' => true,
            'interval_minutes' => 15,
            'jitter_seconds' => 0,
            'next_run_at' => now()->addMinutes(20),
        ]);

        Http::fake([
            $source->url => Http::response($this->singleItemRss(), 200, ['Content-Type' => 'application/rss+xml']),
        ]);

        $initialRunCount = BotRun::query()->count();
        $exitCode = Artisan::call('bots:schedules:run', ['--limit' => 20]);

        $this->assertSame(0, $exitCode);
        $this->assertSame($initialRunCount + 1, BotRun::query()->count());

        $dueSchedule->refresh();
        $futureSchedule->refresh();

        $this->assertNotNull($dueSchedule->last_run_at);
        $this->assertNotNull($dueSchedule->next_run_at);
        $this->assertTrue($dueSchedule->next_run_at->gte(now()->subMinute()->addMinutes(14)));
        $this->assertSame('success', (string) $dueSchedule->last_result);

        $this->assertNull($futureSchedule->last_run_at);
        $this->assertSame('success', (string) ($dueSchedule->last_result ?? ''));
    }

    public function test_schedule_command_skips_disabled_schedules(): void
    {
        $botUser = $this->createBotUser('stellarbot');
        $source = $this->createSource('schedule_disabled_source', 'stela');

        $disabledSchedule = BotSchedule::query()->create([
            'bot_user_id' => $botUser->id,
            'source_id' => $source->id,
            'enabled' => false,
            'interval_minutes' => 10,
            'jitter_seconds' => 0,
            'next_run_at' => now()->subMinute(),
        ]);

        $initialRunCount = BotRun::query()->count();
        $exitCode = Artisan::call('bots:schedules:run', ['--limit' => 20]);

        $this->assertSame(0, $exitCode);
        $this->assertSame($initialRunCount, BotRun::query()->count());

        $disabledSchedule->refresh();
        $this->assertNull($disabledSchedule->last_run_at);
        $this->assertNull($disabledSchedule->last_result);
    }

    private function createBotUser(string $username): User
    {
        $user = User::query()->firstOrNew([
            'username' => $username,
        ]);

        $user->forceFill([
            'name' => (string) ($user->name ?: ucfirst(str_replace('bot', '', $username))),
            'email' => null,
            'password' => $user->password ?: 'secret',
            'is_bot' => true,
            'role' => User::ROLE_BOT,
            'is_active' => true,
            'is_admin' => false,
            'requires_email_verification' => false,
        ])->save();

        return $user->fresh() ?? $user;
    }

    private function createSource(string $key, string $identity = 'kozmo'): BotSource
    {
        return BotSource::query()->create([
            'key' => strtolower(trim($key)),
            'name' => 'Scheduled Source',
            'bot_identity' => $identity,
            'source_type' => BotSourceType::RSS->value,
            'url' => 'https://example.test/schedules-feed.xml',
            'is_enabled' => true,
        ]);
    }

    private function singleItemRss(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <title>NASA News Releases</title>
    <item>
      <title>Scheduled RSS Item</title>
      <link>https://www.nasa.gov/news-release/scheduled-rss-item/</link>
      <guid isPermaLink="false">scheduled-rss-item-guid</guid>
      <pubDate>Thu, 19 Feb 2026 08:00:00 GMT</pubDate>
      <description><![CDATA[<p>Body text with enough content length to pass publish validation checks in bots pipeline.</p>]]></description>
    </item>
  </channel>
</rss>
XML;
    }
}
