<?php

namespace Tests\Feature\Bots;

use App\Models\BotActivityLog;
use App\Models\BotItem;
use App\Models\BotRun;
use App\Models\Post;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

class RunBotSourceCommandCoreAndApodTest extends RunBotSourceCommandTestCase
{
    public function test_first_run_creates_bot_items_and_publishes_posts_to_astro_feed(): void
    {
        $source = $this->createSource();
        Http::fake([
            $source->url => Http::response($this->fixtureRss(), 200, ['Content-Type' => 'application/rss+xml']),
        ]);

        $exitCode = Artisan::call('bots:run', ['sourceKey' => $source->key]);

        $this->assertSame(0, $exitCode);
        $this->assertDatabaseCount('bot_items', 2);
        $this->assertDatabaseCount('posts', 2);

        $this->assertSame(2, BotItem::query()->whereNotNull('post_id')->count());
        $this->assertSame(2, BotItem::query()->where('publish_status', 'published')->count());
        $this->assertSame(2, BotItem::query()->whereIn('translation_status', ['done', 'skipped'])->count());

        $run = BotRun::query()->latest('id')->firstOrFail();
        $this->assertSame(0, (int) ($run->stats['skipped_count'] ?? -1));

        $this->assertSame(
            2,
            Post::query()
                ->where('feed_key', 'astro')
                ->where('author_kind', 'bot')
                ->where('bot_identity', 'kozmo')
                ->count()
        );

        $post = Post::query()->latest('id')->firstOrFail();
        $this->assertNotNull($post->bot_item_id);
        $this->assertNotNull($post->ingested_at);
        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'bot_item_id' => $post->bot_item_id,
        ]);
        $this->assertSame('kozmo', (string) data_get($post->meta, 'bot_identity'));
        $this->assertSame('nasa_rss_breaking', (string) data_get($post->meta, 'bot_source_key'));
        $this->assertSame('NASA RSS', (string) data_get($post->meta, 'bot_source_label'));
        $this->assertSame('NASA', (string) data_get($post->meta, 'bot_source_attribution'));
        $this->assertNotSame('', (string) data_get($post->meta, 'source_url'));
        $this->assertSame('bot-engine', (string) data_get($post->meta, 'published_by'));
        $this->assertNotSame('', (string) data_get($post->meta, 'published_at_utc'));

        $kozmoBot = User::query()->where('is_bot', true)->where('username', 'kozmobot')->first();
        $this->assertNotNull($kozmoBot);
        $this->assertSame('Kozmo', (string) $kozmoBot->name);
    }

    public function test_successful_run_updates_source_health_fields(): void
    {
        $source = $this->createSource();
        Http::fake([
            $source->url => Http::response($this->fixtureRss(), 200, ['Content-Type' => 'application/rss+xml']),
        ]);

        $exitCode = Artisan::call('bots:run', ['sourceKey' => $source->key]);

        $this->assertSame(0, $exitCode);

        $source->refresh();
        $this->assertNotNull($source->last_run_at);
        $this->assertNotNull($source->last_success_at);
        $this->assertSame(0, (int) $source->consecutive_failures);
        $this->assertNull($source->last_error_message);
        $this->assertSame(200, (int) $source->last_status_code);
        $this->assertNotNull($source->avg_latency_ms);
    }

    public function test_failed_run_updates_source_health_error_fields(): void
    {
        config()->set('bots.sources.nasa_apod_daily.requires_api_key', true);
        config()->set('services.nasa.key', '');
        config()->set('services.nasa.apod_api_key', '');

        $source = $this->createApodSource();

        $exitCode = Artisan::call('bots:run', ['sourceKey' => $source->key]);
        $this->assertSame(0, $exitCode);

        $source->refresh();
        $this->assertNotNull($source->last_run_at);
        $this->assertNotNull($source->last_error_at);
        $this->assertSame(1, (int) $source->consecutive_failures);
        $this->assertSame(429, (int) $source->last_status_code);
        $this->assertStringContainsString('api kluc', strtolower((string) $source->last_error_message));
    }

    public function test_ingest_attempts_are_logged_as_created_and_skipped_duplicate(): void
    {
        $source = $this->createSource();
        Http::fake([
            $source->url => Http::response($this->fixtureRss(), 200, ['Content-Type' => 'application/rss+xml']),
        ]);

        $firstExitCode = Artisan::call('bots:run', ['sourceKey' => $source->key]);
        $secondExitCode = Artisan::call('bots:run', ['sourceKey' => $source->key]);

        $this->assertSame(0, $firstExitCode);
        $this->assertSame(0, $secondExitCode);

        $this->assertSame(
            2,
            BotActivityLog::query()
                ->where('action', 'ingest')
                ->where('outcome', 'created')
                ->count()
        );
        $this->assertGreaterThanOrEqual(
            2,
            BotActivityLog::query()
                ->where('action', 'ingest')
                ->where('outcome', 'skipped_duplicate')
                ->count()
        );
    }

    public function test_publish_rate_limiter_limits_per_bot_identity_and_logs_skip_reason(): void
    {
        config()->set('bots.publish_rate_limit.enabled', true);
        config()->set('bots.publish_rate_limit.window_seconds', 3600);
        config()->set('bots.publish_rate_limit.default_max_attempts', 1);
        config()->set('bots.publish_rate_limit.identities.kozmo', 1);

        $source = $this->createSource();
        Http::fake([
            $source->url => Http::response($this->fixtureRss(), 200, ['Content-Type' => 'application/rss+xml']),
        ]);

        $exitCode = Artisan::call('bots:run', ['sourceKey' => $source->key]);

        $this->assertSame(0, $exitCode);
        $this->assertDatabaseCount('bot_items', 2);
        $this->assertDatabaseCount('posts', 1);

        $this->assertSame(1, BotItem::query()->where('publish_status', 'published')->count());
        $this->assertSame(1, BotItem::query()->where('publish_status', 'skipped')->count());
        $this->assertSame(1, BotItem::query()->where('meta->skip_reason', 'publish_rate_limited')->count());

        $rateLimitedLog = BotActivityLog::query()
            ->where('action', 'publish')
            ->where('outcome', 'skipped')
            ->where('reason', 'publish_rate_limited')
            ->first();

        $this->assertNotNull($rateLimitedLog);
        $this->assertSame(
            'kozmo',
            (string) ($rateLimitedLog->bot_identity?->value ?? $rateLimitedLog->bot_identity)
        );
        $this->assertGreaterThanOrEqual(1, (int) data_get($rateLimitedLog->meta, 'retry_after_sec', 0));
    }

    public function test_source_label_and_attribution_are_loaded_from_config_mapping(): void
    {
        config()->set('bots.sources.nasa_rss_breaking.label', 'NASA News Wire');
        config()->set('bots.sources.nasa_rss_breaking.attribution', 'NASA Open Data');

        $source = $this->createSource();
        Http::fake([
            $source->url => Http::response($this->fixtureRss(), 200, ['Content-Type' => 'application/rss+xml']),
        ]);

        $exitCode = Artisan::call('bots:run', ['sourceKey' => $source->key]);

        $this->assertSame(0, $exitCode);

        $post = Post::query()->latest('id')->firstOrFail();
        $this->assertSame('NASA News Wire', (string) data_get($post->meta, 'bot_source_label'));
        $this->assertSame('NASA Open Data', (string) data_get($post->meta, 'bot_source_attribution'));
        $this->assertSame('NASA Open Data', (string) data_get($post->meta, 'source_attribution'));
    }

    public function test_second_run_is_idempotent_for_items_and_posts(): void
    {
        $source = $this->createSource();
        Http::fake([
            $source->url => Http::response($this->fixtureRss(), 200, ['Content-Type' => 'application/rss+xml']),
        ]);

        $firstExitCode = Artisan::call('bots:run', ['sourceKey' => $source->key]);
        $this->assertSame(0, $firstExitCode);

        $firstMap = BotItem::query()
            ->where('source_id', $source->id)
            ->pluck('post_id', 'stable_key')
            ->all();

        $secondExitCode = Artisan::call('bots:run', ['sourceKey' => $source->key]);
        $this->assertSame(0, $secondExitCode);

        $this->assertDatabaseCount('bot_items', 2);
        $this->assertDatabaseCount('posts', 2);

        $secondMap = BotItem::query()
            ->where('source_id', $source->id)
            ->pluck('post_id', 'stable_key')
            ->all();

        $this->assertSame($firstMap, $secondMap);

        $run = BotRun::query()->latest('id')->firstOrFail();
        $this->assertSame('success', (string) $run->status->value);
        $this->assertSame(2, (int) ($run->stats['fetched_count'] ?? 0));
        $this->assertSame(0, (int) ($run->stats['new_count'] ?? -1));
        $this->assertSame(2, (int) ($run->stats['dupes_count'] ?? -1));
        $this->assertSame(0, (int) ($run->stats['published_count'] ?? -1));
        $this->assertSame(2, (int) ($run->stats['skipped_count'] ?? -1));
        $this->assertSame(0, (int) ($run->stats['failed_count'] ?? -1));
    }

    public function test_apod_image_run_creates_item_and_publishes_stela_post(): void
    {
        $source = $this->createApodSource();
        Http::fake([
            $source->url . '*' => Http::response($this->apodPayload(), 200, ['Content-Type' => 'application/json']),
            'https://apod.nasa.gov/apod/image/test-hd.jpg*' => Http::response(
                $this->imageFixtureBinary(),
                200,
                ['Content-Type' => 'image/jpeg']
            ),
        ]);

        $exitCode = Artisan::call('bots:run', ['sourceKey' => $source->key]);

        $this->assertSame(0, $exitCode);
        $this->assertDatabaseCount('bot_items', 1);
        $this->assertDatabaseCount('posts', 1);

        $item = BotItem::query()->firstOrFail();
        $this->assertSame('published', (string) $item->publish_status->value);
        $this->assertSame('image', (string) data_get($item->meta, 'media_type'));
        $this->assertSame('2026-02-20', (string) $item->stable_key);
        $this->assertNotNull($item->post_id);

        $post = Post::query()->firstOrFail();
        $this->assertSame('astro', (string) $post->feed_key->value);
        $this->assertSame('bot', (string) $post->author_kind->value);
        $this->assertSame('stela', (string) $post->bot_identity->value);
        $this->assertNotNull($post->attachment_path);
        $this->assertStringContainsString('APOD', (string) $post->content);
        $this->assertStringContainsString('Attribution: NASA APOD | 2026-02-20 | Credit: NASA/ESA', (string) $post->content);
        $this->assertSame('stela', (string) data_get($post->meta, 'bot_identity'));
        $this->assertSame('nasa_apod_daily', (string) data_get($post->meta, 'bot_source_key'));
        $this->assertNotSame('', (string) data_get($post->meta, 'source_url'));

        $stelaBot = User::query()->where('is_bot', true)->where('username', 'stellarbot')->first();
        $this->assertNotNull($stelaBot);
        $this->assertSame('Stela', (string) $stelaBot->name);

        $run = BotRun::query()->latest('id')->firstOrFail();
        $this->assertSame(1, (int) ($run->stats['published_count'] ?? 0));
        $this->assertSame(0, (int) ($run->stats['skipped_count'] ?? 0));
        $this->assertSame(0, (int) ($run->stats['failed_count'] ?? 0));
    }

    public function test_apod_http_429_marks_run_as_rate_limited_with_structured_meta_and_cooldown(): void
    {
        $source = $this->createApodSource();
        Http::fake([
            $source->url . '*' => Http::response([
                'error' => [
                    'code' => 'OVER_RATE_LIMIT',
                    'message' => 'API rate limit exceeded.',
                ],
            ], 429, [
                'Content-Type' => 'application/json',
                'Retry-After' => '120',
            ]),
        ]);

        $exitCode = Artisan::call('bots:run', ['sourceKey' => $source->key]);

        $this->assertSame(0, $exitCode);
        $this->assertDatabaseCount('bot_items', 0);
        $this->assertDatabaseCount('posts', 0);

        $run = BotRun::query()->latest('id')->firstOrFail();
        $this->assertSame('skipped', (string) $run->status->value);
        $this->assertSame('rate_limited', (string) data_get($run->meta, 'failure_reason'));
        $this->assertSame('nasa_apod', (string) data_get($run->meta, 'provider'));
        $this->assertSame(429, (int) data_get($run->meta, 'http_status'));
        $this->assertSame(120, (int) data_get($run->meta, 'retry_after_sec'));
        $this->assertStringContainsString('limit poziadaviek', strtolower((string) data_get($run->meta, 'message')));
        $this->assertNotNull(data_get($run->meta, 'cooldown_until'));

        $source->refresh();
        $this->assertNotNull($source->cooldown_until);
        $this->assertTrue($source->cooldown_until->isFuture());
    }

    public function test_apod_http_429_uses_rss_fallback_when_enabled(): void
    {
        config()->set('bots.sources.nasa_apod_daily.enable_rss_fallback', true);

        $source = $this->createApodSource();
        Http::fake([
            $source->url . '*' => Http::response([
                'error' => [
                    'code' => 'OVER_RATE_LIMIT',
                    'message' => 'API rate limit exceeded.',
                ],
            ], 429, [
                'Content-Type' => 'application/json',
                'Retry-After' => '120',
            ]),
            'https://apod.nasa.gov/apod.rss*' => Http::response(
                $this->apodRssPayload(),
                200,
                ['Content-Type' => 'application/rss+xml']
            ),
            'https://apod.nasa.gov/apod/ap260220.html*' => Http::response(
                $this->apodRssArticleHtml(),
                200,
                ['Content-Type' => 'text/html']
            ),
            'https://apod.nasa.gov/apod/image/test-full.jpg*' => Http::response(
                $this->imageFixtureBinary(),
                200,
                ['Content-Type' => 'image/jpeg']
            ),
        ]);

        $exitCode = Artisan::call('bots:run', ['sourceKey' => $source->key]);

        $this->assertSame(0, $exitCode);
        $this->assertDatabaseCount('bot_items', 1);
        $this->assertDatabaseCount('posts', 1);

        $run = BotRun::query()->latest('id')->firstOrFail();
        $this->assertSame('success', (string) $run->status->value);
        $this->assertSame(1, (int) data_get($run->stats, 'published_count', 0));
        $this->assertNull(data_get($run->meta, 'failure_reason'));

        $item = BotItem::query()->firstOrFail();
        $this->assertSame('apod_rss', (string) data_get($item->meta, 'fallback_source'));
        $this->assertSame('rate_limited', (string) data_get($item->meta, 'fallback_reason'));
        $this->assertSame('2026-02-20', (string) data_get($item->meta, 'apod_date'));
        $this->assertSame('https://apod.nasa.gov/apod/image/test-full.jpg', (string) data_get($item->meta, 'image_url'));
        $this->assertSame('https://apod.nasa.gov/apod/image/test-full.jpg', (string) data_get($item->meta, 'hdurl'));

        $source->refresh();
        $this->assertNull($source->cooldown_until);
    }

    public function test_apod_missing_api_key_marks_run_as_needs_api_key_and_does_not_call_http(): void
    {
        config()->set('bots.sources.nasa_apod_daily.requires_api_key', true);
        config()->set('services.nasa.key', '');
        config()->set('services.nasa.apod_api_key', '');

        $source = $this->createApodSource();
        Http::fake([
            '*' => Http::response(['ok' => true], 200),
        ]);

        $exitCode = Artisan::call('bots:run', ['sourceKey' => $source->key]);

        $this->assertSame(0, $exitCode);
        Http::assertNothingSent();

        $run = BotRun::query()->latest('id')->firstOrFail();
        $this->assertSame('skipped', (string) $run->status->value);
        $this->assertSame('needs_api_key', (string) data_get($run->meta, 'failure_reason'));
        $this->assertSame('nasa_apod', (string) data_get($run->meta, 'provider'));
        $this->assertSame('NASA APOD API vyzaduje API kluc. Pridajte NASA_API_KEY alebo pockajte.', (string) data_get($run->meta, 'message'));
        $this->assertNull(data_get($run->meta, 'cooldown_until'));
        $this->assertNull(data_get($run->meta, 'retry_after_sec'));

        $source->refresh();
        $this->assertNull($source->cooldown_until);
    }

    public function test_publish_repairs_legacy_bot_username_but_preserves_custom_display_name(): void
    {
        config()->set('bots.identities.kozmo.username', 'kozmobot');
        config()->set('bots.identities.kozmo.display_name', 'Kozmo');
        $customDisplayName = 'Legacy Kozmo đźš€';

        $legacyBot = User::factory()->create([
            'is_bot' => true,
            'is_active' => true,
            'email_verified_at' => now(),
            'username' => 'kozmo',
            'name' => $customDisplayName,
            'email' => 'kozmo@astrokomunita.local',
        ]);

        $source = $this->createSource();
        Http::fake([
            $source->url => Http::response($this->fixtureRss(), 200, ['Content-Type' => 'application/rss+xml']),
        ]);

        $exitCode = Artisan::call('bots:run', ['sourceKey' => $source->key]);
        $this->assertSame(0, $exitCode);

        $legacyBot->refresh();
        $this->assertSame('kozmobot', (string) $legacyBot->username);
        $this->assertSame($customDisplayName, (string) $legacyBot->name);
    }

    public function test_apod_video_item_is_published_without_attachment(): void
    {
        $source = $this->createApodSource();
        Http::fake([
            $source->url . '*' => Http::response($this->apodPayload([
                'media_type' => 'video',
                'url' => 'https://www.youtube.com/watch?v=test',
                'hdurl' => null,
            ]), 200, ['Content-Type' => 'application/json']),
        ]);

        $exitCode = Artisan::call('bots:run', ['sourceKey' => $source->key]);

        $this->assertSame(0, $exitCode);
        $this->assertDatabaseCount('bot_items', 1);
        $this->assertDatabaseCount('posts', 1);

        $item = BotItem::query()->firstOrFail();
        $this->assertSame('published', (string) $item->publish_status->value);
        $this->assertNotNull($item->post_id);
        $this->assertNull(data_get($item->meta, 'skip_reason'));

        $post = Post::query()->firstOrFail();
        $this->assertSame('stela', (string) $post->bot_identity->value);
        $this->assertNull($post->attachment_path);
        $this->assertStringContainsString('https://www.youtube.com/watch?v=test', (string) $post->content);

        $run = BotRun::query()->latest('id')->firstOrFail();
        $this->assertSame(1, (int) ($run->stats['published_count'] ?? 0));
        $this->assertSame(0, (int) ($run->stats['skipped_count'] ?? 0));
        $this->assertSame(0, (int) ($run->stats['failed_count'] ?? 0));
    }

    public function test_apod_mp4_video_is_published_with_video_attachment(): void
    {
        $source = $this->createApodSource();
        Http::fake([
            $source->url . '*' => Http::response($this->apodPayload([
                'media_type' => 'video',
                'url' => 'https://apod.nasa.gov/apod/video/test-video.mp4',
                'hdurl' => null,
            ]), 200, ['Content-Type' => 'application/json']),
            'https://apod.nasa.gov/apod/video/test-video.mp4*' => Http::response(
                'FAKE_MP4_BINARY',
                200,
                ['Content-Type' => 'video/mp4', 'Content-Length' => '15']
            ),
        ]);

        $exitCode = Artisan::call('bots:run', ['sourceKey' => $source->key]);

        $this->assertSame(0, $exitCode);
        $this->assertDatabaseCount('bot_items', 1);
        $this->assertDatabaseCount('posts', 1);

        $item = BotItem::query()->firstOrFail();
        $this->assertSame('published', (string) $item->publish_status->value);
        $this->assertNotNull($item->post_id);

        $post = Post::query()->firstOrFail();
        $this->assertNotNull($post->attachment_path);
        $this->assertSame('video/mp4', (string) $post->attachment_mime);
        $this->assertStringContainsString('https://apod.nasa.gov/apod/video/test-video.mp4', (string) $post->content);

        $run = BotRun::query()->latest('id')->firstOrFail();
        $this->assertSame(1, (int) ($run->stats['published_count'] ?? 0));
        $this->assertSame(0, (int) ($run->stats['skipped_count'] ?? 0));
        $this->assertSame(0, (int) ($run->stats['failed_count'] ?? 0));
    }

    public function test_apod_video_item_with_legacy_non_image_skip_reason_is_retried_and_published(): void
    {
        $source = $this->createApodSource();

        BotItem::query()->create([
            'bot_identity' => 'stela',
            'source_id' => $source->id,
            'stable_key' => '2026-02-20',
            'title' => 'APOD Test Title',
            'content' => 'APOD explanation text long enough to pass content checks for publishing.',
            'url' => 'https://www.youtube.com/watch?v=test',
            'published_at' => Carbon::parse('2026-02-20 12:00:00'),
            'fetched_at' => now()->subMinute(),
            'lang_original' => 'en',
            'translation_status' => 'done',
            'publish_status' => 'skipped',
            'meta' => [
                'media_type' => 'video',
                'skip_reason' => 'non_image_media',
            ],
        ]);

        Http::fake([
            $source->url . '*' => Http::response($this->apodPayload([
                'date' => '2026-02-20',
                'media_type' => 'video',
                'url' => 'https://www.youtube.com/watch?v=test',
                'hdurl' => null,
            ]), 200, ['Content-Type' => 'application/json']),
        ]);

        $exitCode = Artisan::call('bots:run', ['sourceKey' => $source->key]);

        $this->assertSame(0, $exitCode);
        $this->assertDatabaseCount('bot_items', 1);
        $this->assertDatabaseCount('posts', 1);

        $item = BotItem::query()->firstOrFail();
        $this->assertSame('published', (string) $item->publish_status->value);
        $this->assertNotNull($item->post_id);
        $this->assertNull(data_get($item->meta, 'skip_reason'));

        $run = BotRun::query()->latest('id')->firstOrFail();
        $this->assertSame(1, (int) ($run->stats['published_count'] ?? 0));
        $this->assertSame(0, (int) ($run->stats['skipped_count'] ?? 0));
        $this->assertSame(0, (int) ($run->stats['failed_count'] ?? 0));
    }

    public function test_apod_duplicate_skipped_item_with_missing_title_is_healed_and_published(): void
    {
        $source = $this->createApodSource();

        $existingItem = BotItem::query()->create([
            'bot_identity' => 'stela',
            'source_id' => $source->id,
            'stable_key' => '2026-02-20',
            'title' => '',
            'summary' => 'APOD explanation text long enough to pass content checks for publishing.',
            'content' => 'APOD explanation text long enough to pass content checks for publishing.',
            'url' => 'https://apod.nasa.gov/apod/image/test-hd.jpg',
            'published_at' => Carbon::parse('2026-02-20 12:00:00'),
            'fetched_at' => now()->subMinute(),
            'lang_original' => 'en',
            'translation_status' => 'done',
            'publish_status' => 'skipped',
            'meta' => [
                'apod_date' => '2026-02-20',
                'image_url' => null,
                'hdurl' => 'https://apod.nasa.gov/apod/image/test-hd.jpg',
                'media_type' => 'image',
                'skip_reason' => 'missing_title_or_url',
            ],
        ]);

        Http::fake([
            $source->url . '*' => Http::response($this->apodPayload([
                'date' => '2026-02-20',
                'title' => null,
                'explanation' => 'APOD explanation text long enough to pass content checks for publishing.',
                'url' => 'https://apod.nasa.gov/apod/image/test.jpg',
                'hdurl' => 'https://apod.nasa.gov/apod/image/test-hd.jpg',
                'media_type' => 'image',
            ]), 200, ['Content-Type' => 'application/json']),
            'https://apod.nasa.gov/apod/image/test-hd.jpg*' => Http::response(
                $this->imageFixtureBinary(),
                200,
                ['Content-Type' => 'image/jpeg']
            ),
        ]);

        $exitCode = Artisan::call('bots:run', ['sourceKey' => $source->key]);

        $this->assertSame(0, $exitCode);
        $this->assertDatabaseCount('bot_items', 1);
        $this->assertDatabaseCount('posts', 1);

        $existingItem->refresh();
        $this->assertNotNull($existingItem->post_id);
        $this->assertSame('published', (string) $existingItem->publish_status->value);
        $this->assertSame('NASA APOD 2026-02-20', (string) $existingItem->title);
        $this->assertSame('https://apod.nasa.gov/apod/image/test-hd.jpg', (string) $existingItem->url);
        $this->assertNull(data_get($existingItem->meta, 'skip_reason'));
        $this->assertTrue((bool) data_get($existingItem->meta, 'field_recovery.title_recovered', false));

        $this->assertDatabaseHas('bot_activity_logs', [
            'action' => 'publish',
            'outcome' => 'published',
            'bot_item_id' => $existingItem->id,
        ]);

        $run = BotRun::query()->latest('id')->firstOrFail();
        $this->assertSame('success', (string) $run->status->value);
        $this->assertSame(1, (int) ($run->stats['dupes_count'] ?? 0));
        $this->assertSame(1, (int) ($run->stats['published_count'] ?? 0));
        $this->assertSame(0, (int) ($run->stats['skipped_count'] ?? 0));
    }

    public function test_apod_run_with_active_cooldown_is_skipped_without_api_call(): void
    {
        $source = $this->createApodSource();
        $source->forceFill([
            'cooldown_until' => now()->addHours(2),
        ])->save();

        Http::fake([
            $source->url . '*' => Http::response($this->apodPayload(), 200, ['Content-Type' => 'application/json']),
        ]);

        $exitCode = Artisan::call('bots:run', ['sourceKey' => $source->key]);

        $this->assertSame(0, $exitCode);
        Http::assertNothingSent();

        $run = BotRun::query()->latest('id')->firstOrFail();
        $this->assertSame('skipped', (string) $run->status->value);
        $this->assertSame('cooldown_rate_limited', (string) data_get($run->meta, 'failure_reason'));
        $this->assertNotNull(data_get($run->meta, 'cooldown_until'));
        $this->assertSame(1, (int) data_get($run->stats, 'skipped_count', 0));
    }

    public function test_apod_run_with_active_cooldown_and_force_manual_override_executes(): void
    {
        $source = $this->createApodSource();
        $source->forceFill([
            'cooldown_until' => now()->addHours(2),
        ])->save();

        Http::fake([
            $source->url . '*' => Http::response($this->apodPayload(), 200, ['Content-Type' => 'application/json']),
            'https://apod.nasa.gov/apod/image/test-hd.jpg*' => Http::response(
                $this->imageFixtureBinary(),
                200,
                ['Content-Type' => 'image/jpeg']
            ),
        ]);

        $exitCode = Artisan::call('bots:run', [
            'sourceKey' => $source->key,
            '--force-manual-override' => true,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertDatabaseCount('posts', 1);

        $run = BotRun::query()->latest('id')->firstOrFail();
        $this->assertSame('success', (string) $run->status->value);
        $this->assertSame(1, (int) data_get($run->stats, 'published_count', 0));
        $this->assertTrue((bool) data_get($run->meta, 'cooldown_bypassed', false));

        $source->refresh();
        $this->assertNull($source->cooldown_until);
    }

    public function test_apod_image_larger_than_policy_limit_is_skipped(): void
    {
        config()->set('bots.stela_image_max_bytes', 1024);

        $source = $this->createApodSource();
        Http::fake([
            $source->url . '*' => Http::response($this->apodPayload(), 200, ['Content-Type' => 'application/json']),
            'https://apod.nasa.gov/apod/image/test-hd.jpg*' => Http::response(
                str_repeat('x', 2048),
                200,
                ['Content-Type' => 'image/jpeg', 'Content-Length' => '2048']
            ),
        ]);

        $exitCode = Artisan::call('bots:run', ['sourceKey' => $source->key]);

        $this->assertSame(0, $exitCode);
        $this->assertDatabaseCount('posts', 0);

        $item = BotItem::query()->firstOrFail();
        $this->assertSame('skipped', (string) $item->publish_status->value);
        $this->assertSame('image_policy_violation', (string) data_get($item->meta, 'skip_reason'));
    }

    public function test_apod_image_with_non_image_mime_is_skipped_by_policy(): void
    {
        $source = $this->createApodSource();
        Http::fake([
            $source->url . '*' => Http::response($this->apodPayload(), 200, ['Content-Type' => 'application/json']),
            'https://apod.nasa.gov/apod/image/test-hd.jpg*' => Http::response(
                '<html>not an image</html>',
                200,
                ['Content-Type' => 'text/html']
            ),
        ]);

        $exitCode = Artisan::call('bots:run', ['sourceKey' => $source->key]);

        $this->assertSame(0, $exitCode);
        $this->assertDatabaseCount('posts', 0);

        $item = BotItem::query()->firstOrFail();
        $this->assertSame('skipped', (string) $item->publish_status->value);
        $this->assertSame('image_policy_violation', (string) data_get($item->meta, 'skip_reason'));
    }

    public function test_apod_second_run_is_idempotent_and_does_not_create_duplicate_posts(): void
    {
        $source = $this->createApodSource();
        Http::fake([
            $source->url . '*' => Http::response($this->apodPayload(), 200, ['Content-Type' => 'application/json']),
            'https://apod.nasa.gov/apod/image/test-hd.jpg*' => Http::response(
                $this->imageFixtureBinary(),
                200,
                ['Content-Type' => 'image/jpeg']
            ),
        ]);

        $firstExitCode = Artisan::call('bots:run', ['sourceKey' => $source->key]);
        $this->assertSame(0, $firstExitCode);

        $firstMap = BotItem::query()
            ->where('source_id', $source->id)
            ->pluck('post_id', 'stable_key')
            ->all();

        $secondExitCode = Artisan::call('bots:run', ['sourceKey' => $source->key]);
        $this->assertSame(0, $secondExitCode);

        $this->assertDatabaseCount('bot_items', 1);
        $this->assertDatabaseCount('posts', 1);

        $secondMap = BotItem::query()
            ->where('source_id', $source->id)
            ->pluck('post_id', 'stable_key')
            ->all();

        $this->assertSame($firstMap, $secondMap);

        $run = BotRun::query()->latest('id')->firstOrFail();
        $this->assertSame('success', (string) $run->status->value);
        $this->assertSame(1, (int) ($run->stats['fetched_count'] ?? 0));
        $this->assertSame(0, (int) ($run->stats['new_count'] ?? -1));
        $this->assertSame(1, (int) ($run->stats['dupes_count'] ?? -1));
        $this->assertSame(0, (int) ($run->stats['published_count'] ?? -1));
        $this->assertSame(1, (int) ($run->stats['skipped_count'] ?? -1));
        $this->assertSame(0, (int) ($run->stats['failed_count'] ?? -1));
    }

}
