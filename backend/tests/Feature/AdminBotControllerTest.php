<?php

namespace Tests\Feature;

use App\Enums\BotSourceType;
use App\Models\BotActivityLog;
use App\Models\BotItem;
use App\Models\BotRun;
use App\Models\BotSource;
use App\Models\Post;
use App\Models\AppSetting;
use App\Models\User;
use App\Enums\BotRunFailureReason;
use App\Services\Bots\Contracts\BotTranslationServiceInterface;
use App\Services\Translation\Exceptions\TranslationProviderUnavailableException;
use App\Services\Translation\Exceptions\TranslationTimeoutException;
use Carbon\Carbon;
use Database\Seeders\BotSourceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminBotControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('moderation.enabled', false);
    }

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

    public function test_admin_get_overview_returns_bot_metrics_payload_shape(): void
    {
        $botUser = User::factory()->create([
            'is_bot' => true,
            'role' => User::ROLE_BOT,
            'username' => 'kozmobot',
            'email' => null,
        ]);

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

        $this->assertSame('kozmobot', (string) data_get($response->json(), 'bots.0.username'));
        $this->assertSame(1, (int) data_get($response->json(), 'bots.0.posts_24h'));
        $this->assertSame(1, (int) data_get($response->json(), 'bots.0.errors_24h'));
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

        $this->assertSame(0, Post::query()->count());
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

    public function test_admin_translation_test_endpoint_returns_provider_and_translated_text(): void
    {
        $this->actingAsAdmin();

        config()->set('bots.translation.primary', 'libretranslate');
        config()->set('bots.translation.fallback', 'none');
        config()->set('bots.translation.timeout_sec', 5);
        config()->set('bots.translation.libretranslate.url', 'http://translation.test');
        config()->set('bots.translation.quality.enabled', false);

        Http::fake([
            'http://translation.test/*' => Http::response([
                'translatedText' => 'SK Test translation payload.',
            ], 200),
        ]);

        $response = $this->postJson('/api/admin/bots/translation/test', [
            'text' => 'Test translation payload.',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('provider', 'libretranslate')
            ->assertJsonPath('translated_text', 'SK Test translation payload.')
            ->assertJsonPath('mode', 'lt_only')
            ->assertJsonPath('provider_chain.0', 'libretranslate');

        $this->assertGreaterThanOrEqual(0, (int) $response->json('latency_ms'));
    }

    public function test_admin_translation_test_endpoint_maps_timeout_to_structured_failure_reason(): void
    {
        $this->actingAsAdmin();

        $this->app->bind(BotTranslationServiceInterface::class, function () {
            return new class implements BotTranslationServiceInterface {
                public function translate(?string $title, ?string $content, string $to = 'sk'): array
                {
                    throw new TranslationTimeoutException('ollama', 'Timeout during test.');
                }
            };
        });

        $response = $this->postJson('/api/admin/bots/translation/test', ['text' => 'Hello']);
        $response
            ->assertStatus(504)
            ->assertJsonPath('failure_reason', BotRunFailureReason::TRANSLATION_TIMEOUT->value);
    }

    public function test_admin_translation_test_endpoint_returns_only_known_failure_reason_values(): void
    {
        $this->actingAsAdmin();

        $this->app->bind(BotTranslationServiceInterface::class, function () {
            return new class implements BotTranslationServiceInterface {
                public function translate(?string $title, ?string $content, string $to = 'sk'): array
                {
                    throw new \RuntimeException('Unknown translation failure');
                }
            };
        });

        $response = $this->postJson('/api/admin/bots/translation/test', ['text' => 'Hello']);
        $reason = (string) $response->json('failure_reason');

        $response->assertStatus(422);
        $this->assertNotNull(BotRunFailureReason::tryFrom($reason));
    }

    public function test_admin_translation_health_endpoint_returns_provider_config_without_secrets(): void
    {
        $this->actingAsAdmin();

        config()->set('bots.translation.primary', 'libretranslate');
        config()->set('bots.translation.fallback', 'none');
        config()->set('bots.translation.timeout_sec', 12);
        config()->set('bots.translation.libretranslate.url', 'http://localhost:5000');

        $this->app->bind(BotTranslationServiceInterface::class, function () {
            return new class implements BotTranslationServiceInterface {
                public function translate(?string $title, ?string $content, string $to = 'sk'): array
                {
                    return [
                        'translated_title' => 'ok',
                        'translated_content' => null,
                        'title_translated' => 'ok',
                        'content_translated' => null,
                        'status' => 'done',
                        'meta' => ['provider' => 'libretranslate'],
                    ];
                }
            };
        });

        $response = $this->getJson('/api/admin/bots/translation/health');
        $response
            ->assertOk()
            ->assertJsonPath('provider', 'libretranslate')
            ->assertJsonPath('simulate_outage_provider', 'none')
            ->assertJsonPath('degraded', false)
            ->assertJsonPath('result.ok', true)
            ->assertJsonMissingPath('api_key');
    }

    public function test_admin_translation_health_endpoint_reports_provider_unavailable_error_type(): void
    {
        $this->actingAsAdmin();

        $this->app->bind(BotTranslationServiceInterface::class, function () {
            return new class implements BotTranslationServiceInterface {
                public function translate(?string $title, ?string $content, string $to = 'sk'): array
                {
                    throw new TranslationProviderUnavailableException('libretranslate', 'Cannot connect');
                }
            };
        });

        $response = $this->getJson('/api/admin/bots/translation/health');
        $response
            ->assertOk()
            ->assertJsonPath('degraded', false)
            ->assertJsonPath('result.ok', false)
            ->assertJsonPath('result.error_type', BotRunFailureReason::PROVIDER_UNAVAILABLE->value);
    }

    public function test_admin_translation_health_endpoint_reports_degraded_when_primary_fails_but_fallback_succeeds(): void
    {
        $this->actingAsAdmin();

        config()->set('bots.translation.primary', 'ollama');
        config()->set('bots.translation.fallback', 'libretranslate');
        AppSetting::put('translation.simulate_outage_provider', 'ollama');

        $this->app->bind(BotTranslationServiceInterface::class, function () {
            return new class implements BotTranslationServiceInterface {
                public function translate(?string $title, ?string $content, string $to = 'sk'): array
                {
                    $currentProvider = strtolower(trim((string) config('bots.translation.primary', '')));
                    $simulatedOutageProvider = strtolower(trim((string) AppSetting::getString('translation.simulate_outage_provider', 'none')));
                    if ($simulatedOutageProvider !== 'none' && $simulatedOutageProvider === $currentProvider) {
                        throw new TranslationProviderUnavailableException('ollama', 'Primary provider unavailable');
                    }

                    return [
                        'translated_title' => 'ok',
                        'translated_content' => null,
                        'title_translated' => 'ok',
                        'content_translated' => null,
                        'status' => 'done',
                        'meta' => ['provider' => 'libretranslate'],
                    ];
                }
            };
        });

        $response = $this->getJson('/api/admin/bots/translation/health');
        $response
            ->assertOk()
            ->assertJsonPath('simulate_outage_provider', 'ollama')
            ->assertJsonPath('degraded', true)
            ->assertJsonPath('result.ok', true)
            ->assertJsonPath('result.error_type', null)
            ->assertJsonPath('result.primary_error_type', BotRunFailureReason::PROVIDER_UNAVAILABLE->value);
    }

    public function test_admin_can_update_translation_simulate_outage_setting(): void
    {
        $this->actingAsAdmin();
        AppSetting::put('translation.simulate_outage_provider', 'none');

        $response = $this->postJson('/api/admin/bots/translation/simulate-outage', [
            'provider' => 'ollama',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('key', 'translation.simulate_outage_provider')
            ->assertJsonPath('old_value', 'none')
            ->assertJsonPath('new_value', 'ollama');

        $this->assertSame('ollama', AppSetting::getString('translation.simulate_outage_provider', 'none'));
    }

    public function test_admin_retry_translation_retries_failed_items_and_marks_them_done(): void
    {
        $source = $this->createRssSource('retry_translation_source');
        $failedItem = $this->createBotItem($source, 'retry-1', now(), [
            'translation_status' => 'failed',
            'translation_error' => 'previous_failure',
            'translation_provider' => null,
            'title' => 'English retry title',
            'content' => 'English retry body long enough to pass validation checks.',
        ]);

        $this->actingAsAdmin();

        config()->set('bots.translation.primary', 'libretranslate');
        config()->set('bots.translation.fallback', 'none');
        config()->set('bots.translation.timeout_sec', 5);
        config()->set('bots.translation.libretranslate.url', 'http://translation.test');

        Http::fake([
            'http://translation.test/*' => function ($request) {
                $sourceText = trim((string) ($request['q'] ?? ''));

                return Http::response([
                    'translatedText' => 'SK ' . $sourceText,
                ], 200);
            },
        ]);

        $response = $this->postJson('/api/admin/bots/translation/retry/' . $source->key . '?limit=10');

        $response
            ->assertOk()
            ->assertJsonPath('source_key', $source->key)
            ->assertJsonPath('retried_count', 1)
            ->assertJsonPath('done_count', 1)
            ->assertJsonPath('failed_count', 0);

        $failedItem->refresh();
        $this->assertSame('done', (string) $failedItem->translation_status->value);
        $this->assertSame('libretranslate', (string) $failedItem->translation_provider);
        $this->assertNull($failedItem->translation_error);
        $this->assertNotNull($failedItem->translated_at);
        $this->assertSame('SK English retry title', (string) $failedItem->title_translated);
    }

    public function test_admin_translation_backfill_updates_existing_post_without_duplicates(): void
    {
        $source = $this->createRssSource('backfill_translation_source');
        $botUser = User::factory()->create([
            'is_bot' => true,
            'username' => 'kozmobot',
            'email' => 'kozmobot-backfill@example.test',
        ]);
        $post = Post::query()->create([
            'user_id' => $botUser->id,
            'feed_key' => 'astro',
            'author_kind' => 'bot',
            'bot_identity' => 'kozmo',
            'content' => 'NASA | English backfill title' . "\n\n" . 'English backfill body long enough for publish.',
            'source_name' => 'bot_' . $source->key,
            'source_uid' => sha1($source->key . '|guid-backfill-admin'),
            'source_url' => 'https://example.test/news/guid-backfill-admin',
            'translation_status' => 'pending',
        ]);

        $item = $this->createBotItem($source, 'guid-backfill-admin', now(), [
            'post_id' => $post->id,
            'translation_status' => 'pending',
            'publish_status' => 'published',
            'title' => 'English backfill title',
            'content' => 'English backfill body long enough for publish.',
            'url' => 'https://example.test/news/guid-backfill-admin',
        ]);

        $this->actingAsAdmin();
        config()->set('bots.translation.primary', 'libretranslate');
        config()->set('bots.translation.fallback', 'none');
        config()->set('bots.translation.timeout_sec', 5);
        config()->set('bots.translation.libretranslate.url', 'http://translation.test');

        Http::fake([
            'http://translation.test/*' => function ($request) {
                $sourceText = trim((string) ($request['q'] ?? ''));

                return Http::response([
                    'translatedText' => 'Ahoj svet ' . $sourceText,
                ], 200);
            },
        ]);

        $response = $this->postJson('/api/admin/bots/translation/backfill/' . $source->key . '?limit=10');

        $response
            ->assertOk()
            ->assertJsonPath('source_key', $source->key)
            ->assertJsonPath('scanned', 1)
            ->assertJsonPath('updated_posts', 1)
            ->assertJsonPath('failed', 0);

        $item->refresh();
        $post->refresh();
        $this->assertSame('done', (string) $item->translation_status->value);
        $this->assertNotNull($item->translated_at);
        $this->assertStringContainsString('Ahoj svet', (string) $post->content);
        $this->assertSame('done', (string) $post->translation_status);
        $this->assertSame(1, Post::query()->count());
    }

    public function test_admin_translation_backfill_is_idempotent_on_second_call(): void
    {
        $source = $this->createRssSource('backfill_translation_idempotent_source');
        $botUser = User::factory()->create([
            'is_bot' => true,
            'username' => 'kozmobot2',
            'email' => 'kozmobot-backfill2@example.test',
        ]);
        $post = Post::query()->create([
            'user_id' => $botUser->id,
            'feed_key' => 'astro',
            'author_kind' => 'bot',
            'bot_identity' => 'kozmo',
            'content' => 'NASA | English idempotent title' . "\n\n" . 'English idempotent body long enough for publish.',
            'source_name' => 'bot_' . $source->key,
            'source_uid' => sha1($source->key . '|guid-backfill-idempotent'),
            'source_url' => 'https://example.test/news/guid-backfill-idempotent',
            'translation_status' => 'pending',
        ]);

        $this->createBotItem($source, 'guid-backfill-idempotent', now(), [
            'post_id' => $post->id,
            'translation_status' => 'pending',
            'publish_status' => 'published',
            'title' => 'English idempotent title',
            'content' => 'English idempotent body long enough for publish.',
            'url' => 'https://example.test/news/guid-backfill-idempotent',
        ]);

        $this->actingAsAdmin();
        config()->set('bots.translation.primary', 'libretranslate');
        config()->set('bots.translation.fallback', 'none');
        config()->set('bots.translation.timeout_sec', 5);
        config()->set('bots.translation.libretranslate.url', 'http://translation.test');

        Http::fake([
            'http://translation.test/*' => function ($request) {
                $sourceText = trim((string) ($request['q'] ?? ''));

                return Http::response([
                    'translatedText' => 'Ahoj svet ' . $sourceText,
                ], 200);
            },
        ]);

        $first = $this->postJson('/api/admin/bots/translation/backfill/' . $source->key . '?limit=10');
        $second = $this->postJson('/api/admin/bots/translation/backfill/' . $source->key . '?limit=10');

        $first
            ->assertOk()
            ->assertJsonPath('updated_posts', 1);

        $second
            ->assertOk()
            ->assertJsonPath('updated_posts', 0);
    }

    public function test_admin_publish_item_endpoint_publishes_pending_item_and_marks_manual_audit(): void
    {
        $source = $this->createRssSource('publish_single_source');
        $item = $this->createBotItem($source, 'publish-single', now(), [
            'run_id' => null,
            'publish_status' => 'pending',
            'translation_status' => 'done',
            'title' => 'Publish single item',
            'content' => 'Content long enough for publish validation to pass quickly.',
            'url' => 'https://example.test/publish-single',
        ]);

        $this->actingAsAdmin();

        $response = $this->postJson('/api/admin/bots/items/' . $item->id . '/publish');

        $response
            ->assertOk()
            ->assertJsonPath('item.id', $item->id)
            ->assertJsonPath('item.publish_status', 'published')
            ->assertJsonPath('item.published_manually', true);

        $item->refresh();
        $this->assertNotNull($item->post_id);
        $this->assertSame('published', (string) $item->publish_status->value);
        $this->assertTrue((bool) data_get($item->meta, 'published_manually'));
        $this->assertNotSame('', (string) data_get($item->meta, 'manual_published_at'));
    }

    public function test_admin_publish_item_endpoint_rejects_skipped_item(): void
    {
        $source = $this->createRssSource('publish_single_skipped_source');
        $item = $this->createBotItem($source, 'publish-single-skipped', now(), [
            'publish_status' => 'skipped',
            'meta' => ['skip_reason' => 'missing_title_or_url'],
        ]);

        $this->actingAsAdmin();

        $this->postJson('/api/admin/bots/items/' . $item->id . '/publish')
            ->assertStatus(422)
            ->assertJsonPath('skip_reason', 'missing_title_or_url');
    }

    public function test_admin_publish_run_endpoint_respects_publish_limit(): void
    {
        $source = $this->createRssSource('publish_run_source');
        $run = BotRun::query()->create([
            'bot_identity' => 'kozmo',
            'source_id' => $source->id,
            'started_at' => now()->subMinutes(2),
            'finished_at' => now()->subMinute(),
            'status' => 'success',
            'stats' => ['published_count' => 0],
            'error_text' => null,
            'meta' => ['run_context' => 'admin', 'mode' => 'dry'],
        ]);

        for ($i = 1; $i <= 3; $i++) {
            $this->createBotItem($source, 'publish-run-' . $i, now()->addSeconds($i), [
                'run_id' => $run->id,
                'publish_status' => 'pending',
                'translation_status' => 'done',
                'title' => 'Publish run item ' . $i,
                'content' => 'Content long enough for publish validation number ' . $i . '.',
                'url' => 'https://example.test/publish-run-' . $i,
            ]);
        }

        $this->actingAsAdmin();

        $response = $this->postJson('/api/admin/bots/runs/' . $run->id . '/publish', [
            'publish_limit' => 2,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('run_id', $run->id)
            ->assertJsonPath('publish_limit', 2)
            ->assertJsonPath('attempted_count', 2)
            ->assertJsonPath('published_count', 2);

        $this->assertSame(2, BotItem::query()->where('run_id', $run->id)->whereNotNull('post_id')->count());
        $this->assertSame(1, BotItem::query()->where('run_id', $run->id)->whereNull('post_id')->count());
    }

    public function test_admin_delete_item_post_endpoint_deletes_post_and_unlinks_item(): void
    {
        $source = $this->createRssSource('delete_item_post_source');
        $botUser = User::factory()->create([
            'is_bot' => true,
            'username' => 'kozmo',
            'email' => 'kozmo.delete@example.test',
        ]);

        $post = Post::query()->create([
            'user_id' => $botUser->id,
            'feed_key' => 'astro',
            'author_kind' => 'bot',
            'bot_identity' => 'kozmo',
            'content' => 'Bot post for delete endpoint test.',
            'moderation_status' => 'ok',
        ]);

        $item = $this->createBotItem($source, 'delete-item-post', now(), [
            'post_id' => $post->id,
            'publish_status' => 'published',
            'translation_status' => 'done',
            'meta' => [
                'published_manually' => true,
            ],
        ]);

        $this->actingAsAdmin();

        $response = $this->deleteJson('/api/admin/bots/items/' . $item->id . '/post');

        $response
            ->assertOk()
            ->assertJsonPath('deleted_post_id', $post->id)
            ->assertJsonPath('item.id', $item->id)
            ->assertJsonPath('item.post_id', null)
            ->assertJsonPath('item.publish_status', 'pending');

        $this->assertDatabaseMissing('posts', ['id' => $post->id]);

        $item->refresh();
        $this->assertNull($item->post_id);
        $this->assertSame('pending', (string) $item->publish_status->value);
        $this->assertTrue((bool) data_get($item->meta, 'deleted_manually'));
        $this->assertSame($post->id, (int) data_get($item->meta, 'deleted_post_id'));
    }

    public function test_admin_delete_item_post_endpoint_rejects_item_without_post_link(): void
    {
        $source = $this->createRssSource('delete_item_no_post_source');
        $item = $this->createBotItem($source, 'delete-item-no-post', now(), [
            'post_id' => null,
            'publish_status' => 'pending',
        ]);

        $this->actingAsAdmin();

        $this->deleteJson('/api/admin/bots/items/' . $item->id . '/post')
            ->assertStatus(422)
            ->assertJsonPath('message', 'Item has no published post to delete.');
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

    private function createRssSource(string $key): BotSource
    {
        return BotSource::query()->create([
            'key' => strtolower(trim($key)),
            'bot_identity' => 'kozmo',
            'source_type' => BotSourceType::RSS->value,
            'url' => 'https://example.test/bot-rss.xml',
            'is_enabled' => true,
            'schedule' => null,
        ]);
    }

    /**
     * @param array<string,mixed> $overrides
     */
    private function createBotItem(BotSource $source, string $stableKey, Carbon $fetchedAt, array $overrides = []): BotItem
    {
        $payload = array_replace([
            'bot_identity' => 'kozmo',
            'source_id' => $source->id,
            'run_id' => null,
            'post_id' => null,
            'stable_key' => $stableKey,
            'title' => 'Test item title',
            'summary' => 'Summary text long enough for content checks.',
            'content' => 'Summary text long enough for content checks.',
            'url' => 'https://example.test/item/' . rawurlencode($stableKey),
            'published_at' => null,
            'fetched_at' => $fetchedAt,
            'lang_original' => 'en',
            'lang_detected' => null,
            'title_translated' => null,
            'content_translated' => null,
            'translation_status' => 'pending',
            'publish_status' => 'pending',
            'meta' => null,
        ], $overrides);

        return BotItem::query()->create($payload);
    }

    private function singleItemRss(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <title>NASA News Releases</title>
    <item>
      <title>Admin RSS Item</title>
      <link>https://www.nasa.gov/news-release/admin-rss-item/</link>
      <guid isPermaLink="false">admin-rss-item-guid</guid>
      <pubDate>Thu, 19 Feb 2026 08:00:00 GMT</pubDate>
      <description><![CDATA[<p>Body text with enough content length to pass publish validation checks in bots pipeline.</p>]]></description>
    </item>
  </channel>
</rss>
XML;
    }
}
