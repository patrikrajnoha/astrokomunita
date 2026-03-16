<?php

namespace Tests\Feature;

use App\Enums\BotRunFailureReason;
use App\Models\AppSetting;
use App\Models\BotActivityLog;
use App\Models\BotItem;
use App\Models\BotRun;
use App\Models\BotSchedule;
use App\Models\BotSource;
use App\Models\Post;
use App\Models\User;
use App\Services\Bots\Contracts\BotTranslationServiceInterface;
use App\Services\Translation\Exceptions\TranslationProviderUnavailableException;
use App\Services\Translation\Exceptions\TranslationTimeoutException;
use Carbon\Carbon;
use Database\Seeders\BotSourceSeeder;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

class AdminBotControllerAccessAndRunsTest extends AdminBotControllerTestCase
{
    public function test_non_admin_gets_403_for_all_bot_admin_endpoints(): void
    {
        $source = $this->createRssSource('secure_rss_source');

        $user = User::factory()->create([
            'is_admin' => false,
            'role' => 'user',
            'is_active' => true,
        ]);
        Sanctum::actingAs($user);

        $this->getJson('/api/admin/bots/overview')->assertStatus(403);
        $this->getJson('/api/admin/bots/sources')->assertStatus(403);
        $this->patchJson('/api/admin/bots/sources/1', ['is_enabled' => true])->assertStatus(403);
        $this->postJson('/api/admin/bots/sources/1/reset-health')->assertStatus(403);
        $this->postJson('/api/admin/bots/sources/1/clear-cooldown')->assertStatus(403);
        $this->postJson('/api/admin/bots/sources/1/revive')->assertStatus(403);
        $this->getJson('/api/admin/bots/runs')->assertStatus(403);
        $this->getJson('/api/admin/bots/activity')->assertStatus(403);
        $this->getJson('/api/admin/bots/schedules')->assertStatus(403);
        $this->postJson('/api/admin/bots/schedules', [])->assertStatus(403);
        $this->patchJson('/api/admin/bots/schedules/1', ['enabled' => false])->assertStatus(403);
        $this->deleteJson('/api/admin/bots/schedules/1')->assertStatus(403);
        $this->getJson('/api/admin/bots/items?run_id=1')->assertStatus(403);
        $this->postJson('/api/admin/bots/run/' . $source->key)->assertStatus(403);
        $this->postJson('/api/admin/bots/translation/test')->assertStatus(403);
        $this->getJson('/api/admin/bots/translation/health')->assertStatus(403);
        $this->postJson('/api/admin/bots/translation/simulate-outage', ['provider' => 'none'])->assertStatus(403);
        $this->postJson('/api/admin/bots/translation/retry/' . $source->key)->assertStatus(403);
        $this->postJson('/api/admin/bots/translation/backfill/' . $source->key)->assertStatus(403);
        $this->postJson('/api/admin/bots/items/1/publish')->assertStatus(403);
        $this->deleteJson('/api/admin/bots/items/1/post')->assertStatus(403);
        $this->getJson('/api/admin/bots/post-retention')->assertStatus(403);
        $this->patchJson('/api/admin/bots/post-retention', ['enabled' => true])->assertStatus(403);
        $this->postJson('/api/admin/bots/post-retention/cleanup')->assertStatus(403);
        $this->postJson('/api/admin/bots/runs/1/publish')->assertStatus(403);
    }

    public function test_admin_get_sources_returns_seeded_bot_sources(): void
    {
        $this->seed(BotSourceSeeder::class);
        $this->actingAsAdmin();

        $response = $this->getJson('/api/admin/bots/sources');

        $response
            ->assertOk()
            ->assertJsonCount(3, 'data');

        $keys = collect($response->json('data'))->pluck('key')->all();
        $this->assertContains('nasa_rss_breaking', $keys);
        $this->assertContains('nasa_apod_daily', $keys);
        $this->assertContains('wiki_onthisday_astronomy', $keys);
    }

    public function test_admin_get_sources_auto_syncs_defaults_when_table_is_empty(): void
    {
        BotSchedule::query()->delete();
        BotSource::query()->delete();

        $this->assertSame(0, BotSource::query()->count());
        $this->actingAsAdmin();

        $response = $this->getJson('/api/admin/bots/sources');

        $response
            ->assertOk()
            ->assertJsonCount(3, 'data');

        $keys = collect($response->json('data'))->pluck('key')->all();
        $this->assertContains('nasa_rss_breaking', $keys);
        $this->assertContains('nasa_apod_daily', $keys);
        $this->assertContains('wiki_onthisday_astronomy', $keys);
        $this->assertSame(3, BotSource::query()->count());
    }

    public function test_admin_get_schedules_auto_syncs_default_bot_schedules(): void
    {
        BotSchedule::query()->delete();
        BotSource::query()->delete();

        $this->assertSame(0, BotSource::query()->count());
        $this->assertSame(0, BotSchedule::query()->count());
        $this->actingAsAdmin();

        $response = $this->getJson('/api/admin/bots/schedules');

        $response
            ->assertOk()
            ->assertJsonPath('total', 3)
            ->assertJsonCount(3, 'data');

        $rows = collect($response->json('data'));
        $sourceKeys = $rows->pluck('source.key')->all();
        $usernamesBySource = $rows
            ->mapWithKeys(fn (array $row): array => [
                (string) data_get($row, 'source.key') => (string) data_get($row, 'bot_user.username'),
            ])
            ->all();

        $this->assertContains('nasa_rss_breaking', $sourceKeys);
        $this->assertContains('nasa_apod_daily', $sourceKeys);
        $this->assertContains('wiki_onthisday_astronomy', $sourceKeys);
        $this->assertSame('kozmobot', $usernamesBySource['nasa_rss_breaking'] ?? null);
        $this->assertSame('stellarbot', $usernamesBySource['nasa_apod_daily'] ?? null);
        $this->assertSame('kozmobot', $usernamesBySource['wiki_onthisday_astronomy'] ?? null);

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
    }

    public function test_admin_get_overview_returns_bot_metrics_payload_shape(): void
    {
        $botUser = User::query()->firstOrNew([
            'username' => 'kozmobot',
        ]);
        $botUser->forceFill([
            'name' => (string) ($botUser->name ?: 'Kozmo'),
            'email' => null,
            'password' => $botUser->password ?: 'secret',
            'is_bot' => true,
            'role' => User::ROLE_BOT,
            'is_active' => true,
            'is_admin' => false,
            'requires_email_verification' => false,
        ])->save();

        $source = $this->createRssSource('overview_source');
        Post::query()->create([
            'user_id' => $botUser->id,
            'feed_key' => 'astro',
            'author_kind' => 'bot',
            'bot_identity' => 'kozmo',
            'content' => 'Overview bot post content.',
            'source_name' => 'bot_' . $source->key,
            'source_uid' => sha1('overview-source'),
            'ingested_at' => now()->subHours(2),
        ]);

        BotActivityLog::query()->create([
            'bot_identity' => 'kozmo',
            'source_id' => $source->id,
            'action' => 'ingest',
            'outcome' => 'skipped_duplicate',
            'reason' => 'stable_key_exists',
            'run_context' => 'scheduled',
            'created_at' => now()->subHours(1),
            'updated_at' => now()->subHours(1),
        ]);
        BotActivityLog::query()->create([
            'bot_identity' => 'kozmo',
            'source_id' => $source->id,
            'action' => 'publish',
            'outcome' => 'failed',
            'reason' => 'exception',
            'run_context' => 'scheduled',
            'created_at' => now()->subMinutes(20),
            'updated_at' => now()->subMinutes(20),
        ]);

        $this->actingAsAdmin();

        $response = $this->getJson('/api/admin/bots/overview');

        $response
            ->assertOk()
            ->assertJsonPath('overall.posts_24h_total', 1)
            ->assertJsonPath('overall.duplicates_24h', 1)
            ->assertJsonPath('overall.failures_24h', 1)
            ->assertJsonStructure([
                'window_hours',
                'generated_at',
                'overall' => [
                    'posts_24h_total',
                    'duplicates_24h',
                    'failures_24h',
                ],
                'bots' => [[
                    'id',
                    'username',
                    'role',
                    'last_activity_at',
                    'posts_24h',
                    'duplicates_24h',
                    'errors_24h',
                    'rate_limit_state' => [
                        'limited',
                        'retry_after_sec',
                        'remaining_attempts',
                        'max_attempts',
                        'window_sec',
                    ],
            ]],
            ]);

        $overviewRow = collect($response->json('bots'))
            ->firstWhere('username', 'kozmobot');

        $this->assertIsArray($overviewRow);
        $this->assertSame(1, (int) data_get($overviewRow, 'posts_24h'));
        $this->assertSame(1, (int) data_get($overviewRow, 'errors_24h'));
    }

    public function test_admin_can_update_source_and_filter_sources_by_state(): void
    {
        $source = $this->createRssSource('source_state_filter');
        $source->forceFill([
            'consecutive_failures' => 2,
            'last_error_at' => now()->subMinutes(15),
            'last_error_message' => 'Temporary upstream failure.',
        ])->save();

        $this->actingAsAdmin();

        $patch = $this->patchJson('/api/admin/bots/sources/' . $source->id, [
            'name' => 'NASA RSS Mirror',
            'url' => 'https://example.test/new-feed.xml',
            'is_enabled' => false,
        ]);

        $patch
            ->assertOk()
            ->assertJsonPath('data.id', $source->id)
            ->assertJsonPath('data.name', 'NASA RSS Mirror')
            ->assertJsonPath('data.url', 'https://example.test/new-feed.xml')
            ->assertJsonPath('data.is_enabled', false);

        $this->assertDatabaseHas('bot_sources', [
            'id' => $source->id,
            'name' => 'NASA RSS Mirror',
            'url' => 'https://example.test/new-feed.xml',
            'is_enabled' => 0,
        ]);

        $this->getJson('/api/admin/bots/sources?enabled=0')
            ->assertOk()
            ->assertJsonFragment([
                'id' => $source->id,
                'key' => $source->key,
            ]);

        $this->getJson('/api/admin/bots/sources?failing_only=1')
            ->assertOk()
            ->assertJsonFragment([
                'id' => $source->id,
                'consecutive_failures' => 2,
            ]);
    }

    public function test_admin_can_crud_bot_schedules(): void
    {
        $source = $this->createRssSource('schedule_crud_source');
        $botUser = User::factory()->create([
            'is_bot' => true,
            'role' => User::ROLE_BOT,
            'username' => 'schedulebot',
            'email' => null,
        ]);

        $this->actingAsAdmin();

        $create = $this->postJson('/api/admin/bots/schedules', [
            'bot_user_id' => $botUser->id,
            'source_id' => $source->id,
            'interval_minutes' => 15,
            'jitter_seconds' => 30,
            'enabled' => true,
        ]);

        $create
            ->assertCreated()
            ->assertJsonPath('data.bot_user_id', $botUser->id)
            ->assertJsonPath('data.source_id', $source->id)
            ->assertJsonPath('data.interval_minutes', 15)
            ->assertJsonPath('data.jitter_seconds', 30)
            ->assertJsonPath('data.enabled', true);

        $scheduleId = (int) data_get($create->json(), 'data.id');
        $this->assertGreaterThan(0, $scheduleId);

        $this->getJson('/api/admin/bots/schedules?bot_user_id=' . $botUser->id)
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', $scheduleId);

        $this->patchJson('/api/admin/bots/schedules/' . $scheduleId, [
            'enabled' => false,
            'interval_minutes' => 30,
            'jitter_seconds' => 90,
        ])
            ->assertOk()
            ->assertJsonPath('data.enabled', false)
            ->assertJsonPath('data.interval_minutes', 30)
            ->assertJsonPath('data.jitter_seconds', 90);

        $this->deleteJson('/api/admin/bots/schedules/' . $scheduleId)
            ->assertOk()
            ->assertJsonPath('deleted', true)
            ->assertJsonPath('id', $scheduleId);

        $this->assertDatabaseMissing('bot_schedules', [
            'id' => $scheduleId,
        ]);
    }

    public function test_admin_post_run_executes_source_and_returns_run_summary_with_stats(): void
    {
        $source = $this->createRssSource('admin_rss_source');
        $this->actingAsAdmin();

        Http::fake([
            $source->url => Http::response($this->singleItemRss(), 200, ['Content-Type' => 'application/rss+xml']),
        ]);

        $response = $this->postJson('/api/admin/bots/run/' . $source->key);

        $response
            ->assertOk()
            ->assertJsonPath('source_key', $source->key)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('stats.fetched_count', 1)
            ->assertJsonPath('stats.published_count', 1);

        $runId = (int) $response->json('run_id');
        $this->assertGreaterThan(0, $runId);
        $this->assertDatabaseHas('bot_runs', [
            'id' => $runId,
            'source_id' => $source->id,
            'status' => 'success',
        ]);
        $this->assertSame('admin', (string) data_get($response->json(), 'meta.run_context'));
        $this->assertSame('auto', (string) data_get($response->json(), 'meta.mode'));
    }

    public function test_admin_post_run_supports_dry_mode_and_publish_limit_meta(): void
    {
        $source = $this->createRssSource('admin_dry_run_source');
        $this->actingAsAdmin();
        $initialPostCount = Post::query()->count();

        Http::fake([
            $source->url => Http::response($this->singleItemRss(), 200, ['Content-Type' => 'application/rss+xml']),
        ]);

        $response = $this->postJson('/api/admin/bots/run/' . $source->key, [
            'mode' => 'dry',
            'publish_limit' => 3,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('stats.published_count', 0)
            ->assertJsonPath('meta.run_context', 'admin')
            ->assertJsonPath('meta.mode', 'dry')
            ->assertJsonPath('meta.publish_limit', 3);

        $this->assertSame($initialPostCount, Post::query()->count());
    }

    public function test_admin_post_run_recovers_stale_unfinished_run_before_starting_new_run(): void
    {
        $source = $this->createRssSource('recover_stale_source');
        $this->actingAsAdmin();

        $staleRun = BotRun::query()->create([
            'bot_identity' => 'kozmo',
            'source_id' => $source->id,
            'started_at' => now()->subMinutes(9),
            'finished_at' => null,
            'status' => 'running',
            'stats' => ['fetched_count' => 1],
            'error_text' => null,
        ]);
        BotRun::query()->whereKey($staleRun->id)->update([
            'created_at' => now()->subMinutes(9),
            'updated_at' => now()->subMinutes(9),
        ]);

        Http::fake([
            $source->url => Http::response($this->singleItemRss(), 200, ['Content-Type' => 'application/rss+xml']),
        ]);

        $response = $this->postJson('/api/admin/bots/run/' . $source->key);

        $response->assertOk();
        $newRunId = (int) $response->json('run_id');

        $staleRun->refresh();
        $this->assertSame('failed', (string) ($staleRun->status?->value ?? $staleRun->status));
        $this->assertNotNull($staleRun->finished_at);
        $this->assertSame('stale_run_recovered', (string) data_get($staleRun->meta, 'failure_reason'));
        $this->assertSame($newRunId, (int) data_get($staleRun->meta, 'recovered_by_run_id'));
    }

    public function test_admin_post_run_finishes_and_publishes_item_even_when_translation_times_out(): void
    {
        $source = $this->createRssSource('translation_timeout_source');
        $this->actingAsAdmin();

        $this->app->bind(BotTranslationServiceInterface::class, function () {
            return new class implements BotTranslationServiceInterface {
                public function translate(?string $title, ?string $content, string $to = 'sk'): array
                {
                    throw new TranslationTimeoutException('ollama', 'Translation timed out for test.');
                }
            };
        });

        Http::fake([
            $source->url => Http::response($this->singleItemRss(), 200, ['Content-Type' => 'application/rss+xml']),
        ]);

        $response = $this->postJson('/api/admin/bots/run/' . $source->key);
        $response
            ->assertOk()
            ->assertJsonPath('status', 'partial');

        $runId = (int) $response->json('run_id');
        $run = BotRun::query()->findOrFail($runId);
        $this->assertNotNull($run->finished_at);
        $this->assertNotNull($run->status);

        $item = BotItem::query()->where('run_id', $runId)->firstOrFail();
        $this->assertSame('failed', (string) ($item->translation_status?->value ?? $item->translation_status));
        $this->assertSame('translation_timeout', (string) data_get($item->meta, 'translation_error_type'));
        $this->assertSame('published', (string) ($item->publish_status?->value ?? $item->publish_status));
        $this->assertNotNull($item->post_id);
    }

    public function test_admin_post_run_finishes_and_publishes_item_when_translation_provider_is_unavailable(): void
    {
        $source = $this->createRssSource('translation_provider_unavailable_source');
        $this->actingAsAdmin();

        $this->app->bind(BotTranslationServiceInterface::class, function () {
            return new class implements BotTranslationServiceInterface {
                public function translate(?string $title, ?string $content, string $to = 'sk'): array
                {
                    throw new TranslationProviderUnavailableException('libretranslate', 'Provider unavailable for test.');
                }
            };
        });

        Http::fake([
            $source->url => Http::response($this->singleItemRss(), 200, ['Content-Type' => 'application/rss+xml']),
        ]);

        $response = $this->postJson('/api/admin/bots/run/' . $source->key);
        $response
            ->assertOk()
            ->assertJsonPath('status', 'partial');

        $runId = (int) $response->json('run_id');
        $run = BotRun::query()->findOrFail($runId);
        $this->assertNotNull($run->finished_at);
        $this->assertNotNull($run->status);

        $item = BotItem::query()->where('run_id', $runId)->firstOrFail();
        $this->assertSame('failed', (string) ($item->translation_status?->value ?? $item->translation_status));
        $this->assertSame(BotRunFailureReason::PROVIDER_UNAVAILABLE->value, (string) data_get($item->meta, 'translation_error_type'));
        $this->assertSame('published', (string) ($item->publish_status?->value ?? $item->publish_status));
        $this->assertNotNull($item->post_id);
    }

    public function test_admin_post_run_is_throttled_on_second_quick_call(): void
    {
        $source = $this->createRssSource('throttle_rss_source');
        $this->actingAsAdmin();

        Http::fake([
            $source->url => Http::response($this->singleItemRss(), 200, ['Content-Type' => 'application/rss+xml']),
        ]);

        $first = $this->postJson('/api/admin/bots/run/' . $source->key);
        $second = $this->postJson('/api/admin/bots/run/' . $source->key);

        $first->assertOk();
        $second
            ->assertStatus(429)
            ->assertJsonStructure(['message', 'retry_after']);

        $this->assertGreaterThanOrEqual(1, (int) $second->json('retry_after'));
    }

    public function test_admin_post_run_force_manual_override_bypasses_throttle(): void
    {
        $source = $this->createRssSource('throttle_override_rss_source');
        $this->actingAsAdmin();

        Http::fake([
            $source->url => Http::response($this->singleItemRss(), 200, ['Content-Type' => 'application/rss+xml']),
        ]);

        $first = $this->postJson('/api/admin/bots/run/' . $source->key);
        $second = $this->postJson('/api/admin/bots/run/' . $source->key, [
            'force_manual_override' => true,
        ]);

        $first->assertOk();
        $second
            ->assertOk()
            ->assertJsonPath('source_key', $source->key);
    }

    public function test_admin_post_run_force_manual_override_bypasses_active_cooldown(): void
    {
        $source = $this->createRssSource('cooldown_override_rss_source');
        $source->forceFill([
            'cooldown_until' => now()->addHours(2),
        ])->save();
        $this->actingAsAdmin();

        Http::fake([
            $source->url => Http::response($this->singleItemRss(), 200, ['Content-Type' => 'application/rss+xml']),
        ]);

        $response = $this->postJson('/api/admin/bots/run/' . $source->key, [
            'force_manual_override' => true,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('source_key', $source->key)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('stats.published_count', 1)
            ->assertJsonPath('meta.cooldown_bypassed', true);

        $source->refresh();
        $this->assertNull($source->cooldown_until);
    }

    public function test_admin_get_runs_returns_latest_run_with_pagination_payload(): void
    {
        $source = $this->createRssSource('runs_rss_source');
        $older = BotRun::query()->create([
            'bot_identity' => 'kozmo',
            'source_id' => $source->id,
            'started_at' => now()->subMinutes(10),
            'finished_at' => now()->subMinutes(9),
            'status' => 'failed',
            'stats' => ['failed_count' => 1],
            'error_text' => str_repeat('x', 1300),
        ]);
        $latest = BotRun::query()->create([
            'bot_identity' => 'kozmo',
            'source_id' => $source->id,
            'started_at' => now()->subMinutes(3),
            'finished_at' => now()->subMinutes(2),
            'status' => 'success',
            'stats' => ['published_count' => 1],
            'error_text' => null,
        ]);

        $this->actingAsAdmin();

        $response = $this->getJson('/api/admin/bots/runs?per_page=20');

        $response
            ->assertOk()
            ->assertJsonPath('current_page', 1)
            ->assertJsonPath('per_page', 20)
            ->assertJsonPath('data.0.id', $latest->id)
            ->assertJsonPath('data.0.source_id', $source->id)
            ->assertJsonPath('data.0.source_key', $source->key)
            ->assertJsonPath('data.0.status', 'success')
            ->assertJsonPath('data.1.id', $older->id);

        $truncatedError = (string) data_get($response->json(), 'data.1.error_text', '');
        $length = function_exists('mb_strlen') ? mb_strlen($truncatedError) : strlen($truncatedError);
        $this->assertLessThanOrEqual(1000, $length);
    }

    public function test_admin_get_activity_returns_paginated_logs_and_filters_by_source(): void
    {
        $source = $this->createRssSource('activity_rss_source');
        $otherSource = $this->createRssSource('activity_other_source');

        $run = BotRun::query()->create([
            'bot_identity' => 'kozmo',
            'source_id' => $source->id,
            'started_at' => now()->subMinutes(6),
            'finished_at' => now()->subMinutes(5),
            'status' => 'partial',
            'stats' => ['skipped_count' => 1],
        ]);
        $item = $this->createBotItem($source, 'activity-stable-key', now()->subMinutes(5));

        BotActivityLog::query()->create([
            'bot_identity' => 'kozmo',
            'source_id' => $source->id,
            'run_id' => $run->id,
            'bot_item_id' => $item->id,
            'post_id' => null,
            'action' => 'publish',
            'outcome' => 'skipped',
            'reason' => 'publish_rate_limited',
            'run_context' => 'admin',
            'message' => 'Rate limit reached.',
            'meta' => ['retry_after_sec' => 120],
            'created_at' => now()->subMinute(),
            'updated_at' => now()->subMinute(),
        ]);
        BotActivityLog::query()->create([
            'bot_identity' => 'stela',
            'source_id' => $otherSource->id,
            'run_id' => null,
            'bot_item_id' => null,
            'post_id' => null,
            'action' => 'run',
            'outcome' => 'success',
            'reason' => null,
            'run_context' => 'scheduled',
            'message' => null,
            'meta' => null,
            'created_at' => now()->subMinutes(10),
            'updated_at' => now()->subMinutes(10),
        ]);

        $this->actingAsAdmin();

        $response = $this->getJson('/api/admin/bots/activity?sourceKey=' . $source->key . '&per_page=20');

        $response
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('per_page', 20)
            ->assertJsonPath('data.0.source_key', $source->key)
            ->assertJsonPath('data.0.action', 'publish')
            ->assertJsonPath('data.0.outcome', 'skipped')
            ->assertJsonPath('data.0.reason', 'publish_rate_limited')
            ->assertJsonPath('data.0.stable_key', 'activity-stable-key')
            ->assertJsonPath('data.0.run_status', 'partial');
    }

    public function test_admin_get_items_by_run_id_returns_only_items_linked_to_run(): void
    {
        $source = $this->createRssSource('items_run_source');
        $otherSource = $this->createRssSource('items_other_source');

        $runStart = Carbon::parse('2026-02-22 10:00:00');
        $runFinish = Carbon::parse('2026-02-22 10:10:00');

        $run = BotRun::query()->create([
            'bot_identity' => 'kozmo',
            'source_id' => $source->id,
            'started_at' => $runStart,
            'finished_at' => $runFinish,
            'status' => 'success',
            'stats' => ['published_count' => 1],
            'error_text' => null,
        ]);
        $otherRun = BotRun::query()->create([
            'bot_identity' => 'kozmo',
            'source_id' => $source->id,
            'started_at' => $runStart->copy()->addHour(),
            'finished_at' => $runFinish->copy()->addHour(),
            'status' => 'success',
            'stats' => ['published_count' => 1],
            'error_text' => null,
        ]);

        $insideOne = $this->createBotItem($source, 'inside-one', $runStart->copy()->addMinute(), [
            'run_id' => $run->id,
            'title' => 'Inside One',
            'publish_status' => 'published',
            'translation_status' => 'done',
            'meta' => [
                'skip_reason' => null,
                'used_translation' => true,
            ],
        ]);
        $insideTwo = $this->createBotItem($source, 'inside-two', $runFinish->copy()->addMinute(), [
            'run_id' => $otherRun->id,
            'title' => 'Inside Two',
            'publish_status' => 'pending',
            'translation_status' => 'pending',
            'meta' => [
                'last_seen_run_id' => $run->id,
            ],
        ]);

        $this->createBotItem($source, 'other-run-id', $runStart->copy()->addMinute(), ['run_id' => $otherRun->id]);
        $this->createBotItem($source, 'legacy-item', $runFinish->copy()->addMinute());
        $this->createBotItem($otherSource, 'other-source', $runStart->copy()->addMinute(), ['run_id' => $run->id]);

        $this->actingAsAdmin();

        $response = $this->getJson('/api/admin/bots/items?run_id=' . $run->id . '&per_page=50');

        $response
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonCount(2, 'data');

        $keys = collect($response->json('data'))->pluck('stable_key')->all();
        $this->assertContains($insideOne->stable_key, $keys);
        $this->assertContains($insideTwo->stable_key, $keys);
        $this->assertNotContains('other-run-id', $keys);
        $this->assertNotContains('legacy-item', $keys);
        $this->assertNotContains('other-source', $keys);
    }

    public function test_admin_get_items_by_run_id_falls_back_to_run_window_for_legacy_rows(): void
    {
        $source = $this->createRssSource('items_fallback_source');

        $runStart = Carbon::parse('2026-02-22 09:00:00');
        $runFinish = Carbon::parse('2026-02-22 09:05:00');
        $run = BotRun::query()->create([
            'bot_identity' => 'kozmo',
            'source_id' => $source->id,
            'started_at' => $runStart,
            'finished_at' => $runFinish,
            'status' => 'success',
            'stats' => ['published_count' => 1],
            'error_text' => null,
        ]);

        $this->createBotItem($source, 'legacy-inside', $runStart->copy()->addMinute());
        $this->createBotItem($source, 'legacy-outside', $runFinish->copy()->addMinutes(5));

        $this->actingAsAdmin();

        $response = $this->getJson('/api/admin/bots/items?run_id=' . $run->id . '&per_page=50');

        $response
            ->assertOk()
            ->assertJsonPath('total', 1);

        $keys = collect($response->json('data'))->pluck('stable_key')->all();
        $this->assertContains('legacy-inside', $keys);
        $this->assertNotContains('legacy-outside', $keys);
    }

    public function test_admin_get_items_pagination_works_for_run_id_filter(): void
    {
        $source = $this->createRssSource('items_pagination_source');

        $runStart = Carbon::parse('2026-02-22 12:00:00');
        $runFinish = Carbon::parse('2026-02-22 12:20:00');

        $run = BotRun::query()->create([
            'bot_identity' => 'kozmo',
            'source_id' => $source->id,
            'started_at' => $runStart,
            'finished_at' => $runFinish,
            'status' => 'success',
            'stats' => ['fetched_count' => 25],
            'error_text' => null,
        ]);

        for ($i = 1; $i <= 25; $i++) {
            $this->createBotItem(
                $source,
                sprintf('item-%02d', $i),
                $runStart->copy()->addSeconds($i),
                ['title' => sprintf('Item %02d', $i)]
            );
        }

        $this->actingAsAdmin();

        $response = $this->getJson('/api/admin/bots/items?run_id=' . $run->id . '&per_page=10&page=2');

        $response
            ->assertOk()
            ->assertJsonPath('current_page', 2)
            ->assertJsonPath('per_page', 10)
            ->assertJsonPath('total', 25)
            ->assertJsonPath('last_page', 3)
            ->assertJsonCount(10, 'data');
    }
}
