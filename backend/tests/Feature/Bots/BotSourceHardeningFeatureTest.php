<?php

namespace Tests\Feature\Bots;

use App\Enums\BotSourceType;
use App\Models\BotActivityLog;
use App\Models\BotSchedule;
use App\Models\BotSource;
use App\Models\User;
use App\Services\Bots\BotSourceHealthPolicy;
use App\Services\Bots\BotSourceHealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BotSourceHardeningFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_source_enters_cooldown_after_repeated_failures(): void
    {
        $source = $this->createSource('hardening_cooldown_source');
        $health = app(BotSourceHealthService::class);

        $health->markFailure($source, 'Failure #1', 500, 100);
        $source->refresh();
        $this->assertNull($source->cooldown_until);
        $this->assertSame(1, (int) $source->consecutive_failures);

        $health->markFailure($source, 'Failure #2', 500, 100);
        $source->refresh();
        $this->assertNull($source->cooldown_until);
        $this->assertSame(2, (int) $source->consecutive_failures);

        $health->markFailure($source, 'Failure #3', 500, 100);
        $source->refresh();
        $this->assertSame(3, (int) $source->consecutive_failures);
        $this->assertNotNull($source->cooldown_until);
        $this->assertTrue($source->cooldown_until->isFuture());
    }

    public function test_cooldown_duration_increases_according_to_policy(): void
    {
        $policy = app(BotSourceHealthPolicy::class);

        $this->assertSame(0, $policy->cooldownSecondsForFailures(1));
        $this->assertSame(0, $policy->cooldownSecondsForFailures(2));
        $this->assertSame(300, $policy->cooldownSecondsForFailures(3));
        $this->assertSame(300, $policy->cooldownSecondsForFailures(4));
        $this->assertSame(1800, $policy->cooldownSecondsForFailures(5));
        $this->assertSame(1800, $policy->cooldownSecondsForFailures(6));
        $this->assertSame(7200, $policy->cooldownSecondsForFailures(7));
    }

    public function test_scheduler_skips_sources_in_cooldown_and_logs_activity(): void
    {
        $botUser = $this->createBotUser('cooldownrunner');
        $source = $this->createSource('hardening_schedule_source');
        $source->forceFill([
            'cooldown_until' => now()->addMinutes(20),
            'consecutive_failures' => 4,
        ])->save();

        $schedule = BotSchedule::query()->create([
            'bot_user_id' => $botUser->id,
            'source_id' => $source->id,
            'enabled' => true,
            'interval_minutes' => 10,
            'jitter_seconds' => 0,
            'next_run_at' => now()->subMinute(),
        ]);

        $exitCode = Artisan::call('bots:schedules:run', ['--limit' => 20]);

        $this->assertSame(0, $exitCode);
        $this->assertDatabaseCount('bot_runs', 0);

        $schedule->refresh();
        $this->assertNotNull($schedule->last_run_at);
        $this->assertSame('skipped', (string) $schedule->last_result);

        $this->assertDatabaseHas('bot_activity_logs', [
            'action' => 'skipped_cooldown',
            'outcome' => 'skipped',
            'reason' => 'source_cooldown_active',
            'source_id' => $source->id,
        ]);
    }

    public function test_successful_run_clears_cooldown_and_resets_failures(): void
    {
        $source = $this->createSource('hardening_success_reset_source');
        $source->forceFill([
            'consecutive_failures' => 6,
            'last_error_message' => 'Broken upstream',
            'cooldown_until' => now()->addMinutes(30),
        ])->save();

        $health = app(BotSourceHealthService::class);
        $health->markSuccess($source, 180, 200);
        $source->refresh();

        $this->assertSame(0, (int) $source->consecutive_failures);
        $this->assertNull($source->cooldown_until);
        $this->assertNull($source->last_error_message);
        $this->assertNotNull($source->last_success_at);
    }

    public function test_dead_source_detection_works_for_threshold_and_stale_success_cases(): void
    {
        $policy = app(BotSourceHealthPolicy::class);

        $thresholdDead = $this->createSource('hardening_dead_threshold_source');
        $thresholdDead->forceFill([
            'consecutive_failures' => 20,
            'last_error_at' => now()->subHour(),
        ])->save();

        $snapshot = $policy->snapshot($thresholdDead);
        $this->assertTrue((bool) ($snapshot['is_dead'] ?? false));
        $this->assertSame('dead', (string) ($snapshot['status'] ?? ''));

        $staleDead = $this->createSource('hardening_dead_stale_source');
        $staleDead->forceFill([
            'consecutive_failures' => 4,
            'last_success_at' => now()->subDays(8),
            'last_error_at' => now()->subMinutes(10),
        ])->save();

        $snapshot = $policy->snapshot($staleDead);
        $this->assertTrue((bool) ($snapshot['is_dead'] ?? false));
        $this->assertSame('dead', (string) ($snapshot['status'] ?? ''));
    }

    public function test_admin_can_reset_clear_cooldown_and_revive_source(): void
    {
        $source = $this->createSource('hardening_admin_actions_source');
        $source->forceFill([
            'is_enabled' => false,
            'consecutive_failures' => 9,
            'last_error_message' => 'Health degraded',
            'cooldown_until' => now()->addMinutes(25),
        ])->save();

        $this->actingAsAdmin();

        $this->postJson('/api/admin/bots/sources/' . $source->id . '/reset-health')
            ->assertOk()
            ->assertJsonPath('data.id', $source->id)
            ->assertJsonPath('data.consecutive_failures', 0)
            ->assertJsonPath('data.cooldown_until', null);

        $source->refresh();
        $this->assertSame(0, (int) $source->consecutive_failures);
        $this->assertNull($source->last_error_message);
        $this->assertNull($source->cooldown_until);
        $this->assertFalse((bool) $source->is_enabled);

        $source->forceFill(['cooldown_until' => now()->addMinutes(10)])->save();
        $this->postJson('/api/admin/bots/sources/' . $source->id . '/clear-cooldown')
            ->assertOk()
            ->assertJsonPath('data.cooldown_until', null);

        $this->postJson('/api/admin/bots/sources/' . $source->id . '/revive')
            ->assertOk()
            ->assertJsonPath('data.id', $source->id)
            ->assertJsonPath('data.is_enabled', true)
            ->assertJsonPath('data.consecutive_failures', 0)
            ->assertJsonPath('data.cooldown_until', null);
    }

    public function test_metrics_payload_includes_cooldown_skips_and_dead_sources(): void
    {
        $sourceOk = $this->createSource('hardening_metrics_ok_source');
        $sourceDead = $this->createSource('hardening_metrics_dead_source', 'stela');
        $sourceDead->forceFill([
            'consecutive_failures' => 20,
            'last_error_at' => now()->subMinutes(30),
            'is_enabled' => false,
        ])->save();

        BotActivityLog::query()->create([
            'source_id' => $sourceOk->id,
            'bot_identity' => 'kozmo',
            'action' => 'skipped_cooldown',
            'outcome' => 'skipped',
            'reason' => 'source_cooldown_active',
            'run_context' => 'scheduled',
            'created_at' => now()->subMinutes(20),
            'updated_at' => now()->subMinutes(20),
        ]);
        BotActivityLog::query()->create([
            'source_id' => $sourceOk->id,
            'bot_identity' => 'kozmo',
            'action' => 'run',
            'outcome' => 'success',
            'run_context' => 'scheduled',
            'created_at' => now()->subMinutes(10),
            'updated_at' => now()->subMinutes(10),
        ]);
        BotActivityLog::query()->create([
            'source_id' => $sourceOk->id,
            'bot_identity' => 'kozmo',
            'action' => 'run',
            'outcome' => 'failed',
            'reason' => 'exception',
            'run_context' => 'scheduled',
            'created_at' => now()->subMinutes(5),
            'updated_at' => now()->subMinutes(5),
        ]);

        $this->actingAsAdmin();

        $overview = $this->getJson('/api/admin/bots/overview');
        $overview
            ->assertOk()
            ->assertJsonPath('overall.cooldown_skips_24h', 1)
            ->assertJsonPath('overall.dead_sources', 1)
            ->assertJsonStructure([
                'overall' => [
                    'success_rate_24h',
                    'failure_rate_24h',
                    'duplicate_rate_24h',
                    'cooldown_skips_24h',
                    'dead_sources',
                ],
            ]);

        $sources = $this->getJson('/api/admin/bots/sources');
        $sources->assertOk();

        $rows = collect($sources->json('data'));
        $okRow = $rows->firstWhere('key', $sourceOk->key);
        $deadRow = $rows->firstWhere('key', $sourceDead->key);

        $this->assertNotNull($okRow);
        $this->assertNotNull($deadRow);
        $this->assertSame(1, (int) data_get($okRow, 'metrics_24h.cooldown_skips_total'));
        $this->assertSame('dead', (string) data_get($deadRow, 'status'));
        $this->assertTrue((bool) data_get($deadRow, 'is_dead'));
    }

    private function actingAsAdmin(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
            'role' => 'admin',
            'is_active' => true,
        ]);

        Sanctum::actingAs($admin);
    }

    private function createBotUser(string $username): User
    {
        return User::factory()->create([
            'is_bot' => true,
            'role' => User::ROLE_BOT,
            'username' => $username,
            'email' => null,
        ]);
    }

    private function createSource(string $key, string $identity = 'kozmo'): BotSource
    {
        return BotSource::query()->create([
            'key' => strtolower(trim($key)),
            'name' => 'Hardening Source',
            'bot_identity' => $identity,
            'source_type' => BotSourceType::RSS->value,
            'url' => 'https://example.test/hardening-feed.xml',
            'is_enabled' => true,
        ]);
    }
}

