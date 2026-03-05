<?php

namespace Tests\Feature\Bots;

use App\Enums\BotSourceType;
use App\Models\BotItem;
use App\Models\BotRun;
use App\Models\BotSource;
use App\Models\Post;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RunBotSourceCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('moderation.enabled', false);
        config()->set('services.nasa.key', 'test-nasa-key');
        config()->set('astrobot.sources.nasa_apod_daily.requires_api_key', true);
        config()->set('astrobot.sources.nasa_apod_daily.rate_limit_backoff_minutes', 360);
    }

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

    public function test_source_label_and_attribution_are_loaded_from_config_mapping(): void
    {
        config()->set('astrobot.sources.nasa_rss_breaking.label', 'NASA News Wire');
        config()->set('astrobot.sources.nasa_rss_breaking.attribution', 'NASA Open Data');

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
        $this->assertStringContainsString('APOD Test Title', (string) $post->content);
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
        $this->assertStringContainsString('rate limit', strtolower((string) data_get($run->meta, 'message')));
        $this->assertNotNull(data_get($run->meta, 'cooldown_until'));

        $source->refresh();
        $this->assertNotNull($source->cooldown_until);
        $this->assertTrue($source->cooldown_until->isFuture());
    }

    public function test_apod_missing_api_key_marks_run_as_needs_api_key_and_does_not_call_http(): void
    {
        config()->set('astrobot.sources.nasa_apod_daily.requires_api_key', true);
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
        $this->assertSame('NASA APOD API requires API key. Add NASA_API_KEY or wait.', (string) data_get($run->meta, 'message'));
        $this->assertNotNull(data_get($run->meta, 'cooldown_until'));
        $this->assertGreaterThan(0, (int) data_get($run->meta, 'retry_after_sec'));

        $source->refresh();
        $this->assertNotNull($source->cooldown_until);
        $this->assertTrue($source->cooldown_until->isFuture());
    }

    public function test_publish_repairs_legacy_bot_username_and_display_name_to_configured_identity(): void
    {
        config()->set('astrobot.identities.kozmo.username', 'kozmobot');
        config()->set('astrobot.identities.kozmo.display_name', 'Kozmo');

        $legacyBot = User::factory()->create([
            'is_bot' => true,
            'is_active' => true,
            'email_verified_at' => now(),
            'username' => 'kozmo',
            'name' => 'Legacy Kozmo',
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
        $this->assertSame('Kozmo', (string) $legacyBot->name);
    }

    public function test_apod_video_item_is_skipped_and_no_post_is_created(): void
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
        $this->assertDatabaseCount('posts', 0);

        $item = BotItem::query()->firstOrFail();
        $this->assertSame('skipped', (string) $item->publish_status->value);
        $this->assertSame('non_image_media', (string) data_get($item->meta, 'skip_reason'));

        $run = BotRun::query()->latest('id')->firstOrFail();
        $this->assertSame(0, (int) ($run->stats['published_count'] ?? 0));
        $this->assertSame(1, (int) ($run->stats['skipped_count'] ?? 0));
        $this->assertSame(0, (int) ($run->stats['failed_count'] ?? 0));
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

    public function test_apod_image_larger_than_policy_limit_is_skipped(): void
    {
        config()->set('astrobot.stela_image_max_bytes', 1024);

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

    public function test_wikipedia_relevant_events_publish_single_kozmo_post(): void
    {
        $this->configureHttpTranslation();

        $this->travelTo(Carbon::parse('2026-02-20 10:00:00'));
        try {
            $source = $this->createWikipediaSource();
            Http::fake([
                $this->wikiEndpointForDate($source->url, now()) => Http::response(
                    $this->wikiFixturePayload(),
                    200,
                    ['Content-Type' => 'application/json']
                ),
                'http://translation.test/translate' => function ($request) {
                    $sourceText = trim((string) ($request['q'] ?? ''));

                    return Http::response([
                        'translatedText' => 'SK ' . $sourceText,
                    ], 200);
                },
            ]);

            $exitCode = Artisan::call('bots:run', ['sourceKey' => $source->key]);

            $this->assertSame(0, $exitCode);
            $this->assertDatabaseCount('bot_items', 1);
            $this->assertDatabaseCount('posts', 1);

            $item = BotItem::query()->firstOrFail();
            $this->assertSame('onthisday:2026-02-20', (string) $item->stable_key);
            $this->assertSame('published', (string) $item->publish_status->value);
            $this->assertSame('done', (string) $item->translation_status->value);
            $this->assertGreaterThanOrEqual(1, count((array) data_get($item->meta, 'selected_events', [])));

            $post = Post::query()->firstOrFail();
            $this->assertSame('astro', (string) $post->feed_key->value);
            $this->assertSame('bot', (string) $post->author_kind->value);
            $this->assertSame('kozmo', (string) $post->bot_identity->value);
            $this->assertStringContainsString('SK Dnes v astro', (string) $post->content);

            $run = BotRun::query()->latest('id')->firstOrFail();
            $this->assertSame(1, (int) ($run->stats['fetched_count'] ?? 0));
            $this->assertSame(1, (int) ($run->stats['published_count'] ?? 0));
            $this->assertSame(0, (int) ($run->stats['skipped_count'] ?? 0));
        } finally {
            $this->travelBack();
        }
    }

    public function test_wikipedia_no_relevant_events_marks_item_skipped_without_post(): void
    {
        config()->set('astrobot.translation_provider', 'dummy');

        $this->travelTo(Carbon::parse('2026-02-20 12:00:00'));
        try {
            $source = $this->createWikipediaSource();
            Http::fake([
                $this->wikiEndpointForDate($source->url, now()) => Http::response(
                    $this->wikiNoRelevantPayload(),
                    200,
                    ['Content-Type' => 'application/json']
                ),
            ]);

            $exitCode = Artisan::call('bots:run', ['sourceKey' => $source->key]);

            $this->assertSame(0, $exitCode);
            $this->assertDatabaseCount('bot_items', 1);
            $this->assertDatabaseCount('posts', 0);

            $item = BotItem::query()->firstOrFail();
            $this->assertSame('onthisday:2026-02-20', (string) $item->stable_key);
            $this->assertSame('skipped', (string) $item->publish_status->value);
            $this->assertSame('no_relevant_events', (string) data_get($item->meta, 'skip_reason'));
            $this->assertCount(0, (array) data_get($item->meta, 'selected_events', []));

            $run = BotRun::query()->latest('id')->firstOrFail();
            $this->assertSame(0, (int) ($run->stats['published_count'] ?? 0));
            $this->assertSame(1, (int) ($run->stats['skipped_count'] ?? 0));
            $this->assertSame(0, (int) ($run->stats['failed_count'] ?? 0));
        } finally {
            $this->travelBack();
        }
    }

    public function test_wikipedia_second_run_same_day_is_idempotent_without_duplicate_post(): void
    {
        config()->set('astrobot.translation_provider', 'dummy');

        $this->travelTo(Carbon::parse('2026-02-20 14:00:00'));
        try {
            $source = $this->createWikipediaSource();
            Http::fake([
                $this->wikiEndpointForDate($source->url, now()) => Http::response(
                    $this->wikiFixturePayload(),
                    200,
                    ['Content-Type' => 'application/json']
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
        } finally {
            $this->travelBack();
        }
    }

    public function test_command_fails_for_disabled_source(): void
    {
        $source = $this->createSource();
        $source->update(['is_enabled' => false]);

        $exitCode = Artisan::call('bots:run', ['sourceKey' => $source->key]);

        $this->assertSame(1, $exitCode);
        $this->assertDatabaseCount('bot_runs', 0);
    }

    public function test_command_fails_when_source_is_missing(): void
    {
        $exitCode = Artisan::call('bots:run', ['sourceKey' => 'missing_source']);

        $this->assertSame(1, $exitCode);
        $this->assertDatabaseCount('bot_runs', 0);
    }

    public function test_fetch_http_500_marks_run_failed_and_does_not_publish(): void
    {
        $source = $this->createSource();
        Http::fake([
            $source->url => Http::response('NASA upstream failure', 500, ['Content-Type' => 'text/plain']),
        ]);

        $exitCode = Artisan::call('bots:run', ['sourceKey' => $source->key]);

        $this->assertSame(1, $exitCode);
        $this->assertDatabaseCount('posts', 0);
        $this->assertDatabaseCount('bot_items', 0);

        $run = BotRun::query()->latest('id')->firstOrFail();
        $this->assertSame('failed', (string) $run->status->value);
        $this->assertNotNull($run->error_text);
        $this->assertStringContainsString('status=500', (string) $run->error_text);
        $this->assertStringContainsString($source->url, (string) $run->error_text);
    }

    public function test_item_without_title_or_link_is_skipped_and_not_published(): void
    {
        $source = $this->createSource();
        Http::fake([
            $source->url => Http::response($this->rssWithMissingTitleAndLink(), 200, ['Content-Type' => 'application/rss+xml']),
        ]);

        $exitCode = Artisan::call('bots:run', ['sourceKey' => $source->key]);

        $this->assertSame(0, $exitCode);
        $this->assertDatabaseCount('posts', 0);
        $this->assertDatabaseCount('bot_items', 1);

        $item = BotItem::query()->firstOrFail();
        $this->assertSame('skipped', (string) $item->publish_status->value);
        $this->assertSame('missing_title_or_url', (string) data_get($item->meta, 'skip_reason'));

        $run = BotRun::query()->latest('id')->firstOrFail();
        $this->assertSame(1, (int) ($run->stats['skipped_count'] ?? 0));
        $this->assertSame(0, (int) ($run->stats['published_count'] ?? 0));
    }

    public function test_item_with_existing_post_id_is_not_republished(): void
    {
        $source = $this->createSource();
        $existingPost = $this->createExistingBotPost();

        $item = BotItem::query()->create([
            'bot_identity' => 'kozmo',
            'source_id' => $source->id,
            'post_id' => $existingPost->id,
            'stable_key' => 'nasa-guid-existing',
            'title' => 'Prelinked title',
            'summary' => 'Prelinked summary with enough content for publish.',
            'content' => 'Prelinked summary with enough content for publish.',
            'url' => 'https://www.nasa.gov/news-release/prelinked/',
            'fetched_at' => now(),
            'translation_status' => 'skipped',
            'publish_status' => 'pending',
            'meta' => null,
        ]);

        Http::fake([
            $source->url => Http::response($this->rssForGuid('nasa-guid-existing'), 200, ['Content-Type' => 'application/rss+xml']),
        ]);

        $exitCode = Artisan::call('bots:run', ['sourceKey' => $source->key]);

        $this->assertSame(0, $exitCode);
        $this->assertDatabaseCount('posts', 1);

        $item->refresh();
        $this->assertSame($existingPost->id, $item->post_id);
        $this->assertSame('published', (string) $item->publish_status->value);
        $this->assertSame('already_linked_post', (string) data_get($item->meta, 'skip_reason'));

        $run = BotRun::query()->latest('id')->firstOrFail();
        $this->assertSame(1, (int) ($run->stats['skipped_count'] ?? 0));
        $this->assertSame(0, (int) ($run->stats['published_count'] ?? 0));
        $this->assertSame(0, (int) ($run->stats['failed_count'] ?? 0));
    }

    public function test_translation_success_marks_item_done_and_publishes_slovak_content(): void
    {
        $this->configureHttpTranslation();

        $source = $this->createSource();
        Http::fake([
            $source->url => Http::response(
                $this->rssSingleItem(
                    guid: 'guid-translation-success',
                    title: 'English title for translation',
                    link: 'https://www.nasa.gov/news-release/translation-success/',
                    description: 'English body text long enough to pass publish validation.'
                ),
                200,
                ['Content-Type' => 'application/rss+xml']
            ),
            'http://translation.test/translate' => function ($request) {
                $sourceText = trim((string) ($request['q'] ?? ''));
                return Http::response([
                    'translatedText' => 'SK ' . $sourceText,
                ], 200);
            },
        ]);

        $exitCode = Artisan::call('bots:run', ['sourceKey' => $source->key]);

        $this->assertSame(0, $exitCode);
        $this->assertDatabaseCount('posts', 1);

        $item = BotItem::query()->firstOrFail();
        $this->assertSame('done', (string) $item->translation_status->value);
        $this->assertTrue((bool) data_get($item->meta, 'used_translation'));

        $post = Post::query()->firstOrFail();
        $this->assertStringContainsString('NASA | SK English title for translation', (string) $post->content);
        $this->assertStringContainsString('SK English body text long enough to pass publish validation.', (string) $post->content);
    }

    public function test_translation_failure_sets_failed_and_publishes_with_english_fallback(): void
    {
        $this->configureHttpTranslation();

        $source = $this->createSource();
        Http::fake([
            $source->url => Http::response(
                $this->rssSingleItem(
                    guid: 'guid-translation-failure',
                    title: 'English fallback title',
                    link: 'https://www.nasa.gov/news-release/translation-failure/',
                    description: 'English fallback body text with enough characters for publish checks.'
                ),
                200,
                ['Content-Type' => 'application/rss+xml']
            ),
            'http://translation.test/translate' => Http::response([
                'error' => 'translation_unavailable',
            ], 500),
        ]);

        $exitCode = Artisan::call('bots:run', ['sourceKey' => $source->key]);

        $this->assertSame(1, $exitCode);
        $this->assertDatabaseCount('posts', 1);

        $item = BotItem::query()->firstOrFail();
        $this->assertSame('failed', (string) $item->translation_status->value);
        $this->assertFalse((bool) data_get($item->meta, 'used_translation'));
        $translationError = (string) data_get($item->meta, 'translation_error');
        $translationErrorLength = function_exists('mb_strlen')
            ? mb_strlen($translationError)
            : strlen($translationError);
        $this->assertLessThanOrEqual(300, $translationErrorLength);

        $post = Post::query()->firstOrFail();
        $this->assertStringContainsString('NASA | English fallback title', (string) $post->content);
        $this->assertStringContainsString('English fallback body text with enough characters for publish checks.', (string) $post->content);

        $run = BotRun::query()->latest('id')->firstOrFail();
        $this->assertSame('partial', (string) $run->status->value);
        $this->assertGreaterThan(0, (int) ($run->stats['failed_count'] ?? 0));
    }

    public function test_translation_cache_key_prevents_duplicate_translation_call_on_second_run(): void
    {
        $this->configureHttpTranslation();

        $source = $this->createSource();
        $translationCalls = 0;

        Http::fake([
            $source->url => Http::response(
                $this->rssSingleItem(
                    guid: 'guid-translation-cache',
                    title: 'English cache title',
                    link: 'https://www.nasa.gov/news-release/translation-cache/',
                    description: 'English cache body text long enough to pass publish validation.'
                ),
                200,
                ['Content-Type' => 'application/rss+xml']
            ),
            'http://translation.test/translate' => function ($request) use (&$translationCalls) {
                $translationCalls++;
                $sourceText = trim((string) ($request['q'] ?? ''));

                return Http::response([
                    'translatedText' => 'SK ' . $sourceText,
                ], 200);
            },
        ]);

        $firstExitCode = Artisan::call('bots:run', ['sourceKey' => $source->key]);
        $this->assertSame(0, $firstExitCode);
        $this->assertSame(2, $translationCalls);

        $item = BotItem::query()->firstOrFail();
        $item->forceFill([
            'translation_status' => 'pending',
            'publish_status' => 'pending',
            'post_id' => null,
        ])->save();

        $secondExitCode = Artisan::call('bots:run', ['sourceKey' => $source->key]);
        $this->assertSame(0, $secondExitCode);
        $this->assertSame(2, $translationCalls);

        $item->refresh();
        $this->assertSame('done', (string) $item->translation_status->value);
    }

    public function test_legacy_dummy_item_without_post_id_is_retranslated_before_publish(): void
    {
        $this->configureHttpTranslation();

        $source = $this->createSource();
        Http::fake([
            $source->url => Http::response(
                $this->rssSingleItem(
                    guid: 'guid-legacy-published-pending-translation',
                    title: 'English legacy title',
                    link: 'https://www.nasa.gov/news-release/legacy-pending-translation/',
                    description: 'English legacy body text long enough to pass publish validation checks.'
                ),
                200,
                ['Content-Type' => 'application/rss+xml']
            ),
            'http://translation.test/translate' => function ($request) {
                $sourceText = trim((string) ($request['q'] ?? ''));

                return Http::response([
                    'translatedText' => 'SK ' . $sourceText,
                ], 200);
            },
        ]);

        $legacyTitle = 'English legacy title';
        $legacyBody = 'English legacy body text long enough to pass publish validation checks.';

        BotItem::query()->create([
            'bot_identity' => 'kozmo',
            'source_id' => $source->id,
            'stable_key' => 'guid-legacy-published-pending-translation',
            'title' => $legacyTitle,
            'summary' => $legacyBody,
            'content' => $legacyBody,
            'url' => 'https://www.nasa.gov/news-release/legacy-pending-translation/',
            'published_at' => now(),
            'fetched_at' => now(),
            'lang_original' => 'en',
            'translation_status' => 'done',
            'publish_status' => 'published',
            'meta' => [
                'translation_cache_key' => sha1('sk|' . $legacyTitle . '|' . $legacyBody),
                'translation' => [
                    'provider' => 'dummy',
                    'reason' => 'translation_not_enabled',
                    'target_lang' => 'sk',
                ],
            ],
        ]);

        $exitCode = Artisan::call('bots:run', ['sourceKey' => $source->key]);
        $this->assertSame(0, $exitCode);

        $item = BotItem::query()->where('stable_key', 'guid-legacy-published-pending-translation')->firstOrFail();
        $this->assertSame('done', (string) $item->translation_status->value);
        $this->assertSame('libretranslate', (string) $item->translation_provider);
        $this->assertStringStartsWith('SK ', (string) $item->title_translated);
        $this->assertNotNull($item->post_id);

        $post = Post::query()->findOrFail($item->post_id);
        $this->assertStringContainsString('NASA | SK English legacy title', (string) $post->content);

        $run = BotRun::query()->latest('id')->firstOrFail();
        $this->assertSame(1, (int) ($run->stats['translation_done_count'] ?? 0));
    }

    public function test_legacy_skipped_item_without_translated_payload_is_retranslated_before_publish(): void
    {
        $this->configureHttpTranslation();

        $source = $this->createSource();
        Http::fake([
            $source->url => Http::response(
                $this->rssSingleItem(
                    guid: 'guid-legacy-skipped-pending-translation',
                    title: 'English skipped title',
                    link: 'https://www.nasa.gov/news-release/legacy-skipped-translation/',
                    description: 'English skipped body text long enough to pass publish validation checks.'
                ),
                200,
                ['Content-Type' => 'application/rss+xml']
            ),
            'http://translation.test/translate' => function ($request) {
                $sourceText = trim((string) ($request['q'] ?? ''));

                return Http::response([
                    'translatedText' => 'SK ' . $sourceText,
                ], 200);
            },
        ]);

        $legacyTitle = 'English skipped title';
        $legacyBody = 'English skipped body text long enough to pass publish validation checks.';

        BotItem::query()->create([
            'bot_identity' => 'kozmo',
            'source_id' => $source->id,
            'stable_key' => 'guid-legacy-skipped-pending-translation',
            'title' => $legacyTitle,
            'summary' => $legacyBody,
            'content' => $legacyBody,
            'url' => 'https://www.nasa.gov/news-release/legacy-skipped-translation/',
            'published_at' => now(),
            'fetched_at' => now(),
            'lang_original' => 'en',
            'translation_status' => 'skipped',
            'publish_status' => 'published',
            'meta' => [
                'translation_cache_key' => sha1('sk|' . $legacyTitle . '|' . $legacyBody),
                'translation' => [
                    'provider' => 'none',
                    'reason' => 'translation_not_enabled',
                    'target_lang' => 'sk',
                ],
            ],
        ]);

        $exitCode = Artisan::call('bots:run', ['sourceKey' => $source->key]);
        $this->assertSame(0, $exitCode);

        $item = BotItem::query()->where('stable_key', 'guid-legacy-skipped-pending-translation')->firstOrFail();
        $this->assertSame('done', (string) $item->translation_status->value);
        $this->assertSame('libretranslate', (string) $item->translation_provider);
        $this->assertStringStartsWith('SK ', (string) $item->title_translated);
        $this->assertNotNull($item->post_id);

        $post = Post::query()->findOrFail($item->post_id);
        $this->assertStringContainsString('NASA | SK English skipped title', (string) $post->content);
    }

    private function createSource(): BotSource
    {
        return BotSource::query()->create([
            'key' => 'nasa_rss_breaking',
            'bot_identity' => 'kozmo',
            'source_type' => BotSourceType::RSS->value,
            'url' => 'https://example.test/nasa.xml',
            'is_enabled' => true,
            'schedule' => null,
        ]);
    }

    private function createApodSource(): BotSource
    {
        return BotSource::query()->create([
            'key' => 'nasa_apod_daily',
            'bot_identity' => 'stela',
            'source_type' => BotSourceType::API->value,
            'url' => 'https://api.nasa.gov/planetary/apod',
            'is_enabled' => true,
            'schedule' => null,
        ]);
    }

    private function createWikipediaSource(): BotSource
    {
        return BotSource::query()->create([
            'key' => 'wiki_onthisday_astronomy',
            'bot_identity' => 'kozmo',
            'source_type' => BotSourceType::WIKIPEDIA->value,
            'url' => 'https://example.test/wiki/onthisday/all',
            'is_enabled' => true,
            'schedule' => null,
        ]);
    }

    private function configureHttpTranslation(string $baseUrl = 'http://translation.test', int $timeoutSeconds = 5): void
    {
        config()->set('astrobot.translation.primary', 'libretranslate');
        config()->set('astrobot.translation.fallback', 'none');
        config()->set('astrobot.translation.libretranslate.url', $baseUrl);
        config()->set('astrobot.translation.libretranslate.timeout_seconds', $timeoutSeconds);

        config()->set('astrobot.translation_provider', 'http');
        config()->set('astrobot.translation_fallback_provider', '');
        config()->set('astrobot.translation_base_url', $baseUrl);
        config()->set('astrobot.translation_timeout_seconds', $timeoutSeconds);
    }

    private function fixtureRss(): string
    {
        return (string) file_get_contents(base_path('tests/Fixtures/nasa_rss.xml'));
    }

    /**
     * @return array<string,mixed>
     */
    private function wikiFixturePayload(): array
    {
        $decoded = json_decode(
            (string) file_get_contents(base_path('tests/Fixtures/wiki_onthisday.json')),
            true
        );

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array<string,mixed>
     */
    private function wikiNoRelevantPayload(): array
    {
        return [
            'events' => [
                [
                    'year' => 1400,
                    'text' => 'A local market opened in a medieval city center.',
                    'pages' => [[
                        'title' => 'Marketplace',
                        'content_urls' => [
                            'desktop' => [
                                'page' => 'https://en.wikipedia.org/wiki/Market_square',
                            ],
                        ],
                    ]],
                ],
                [
                    'year' => 1720,
                    'text' => 'A bridge construction project was completed near a river.',
                    'pages' => [[
                        'title' => 'Bridge',
                        'content_urls' => [
                            'desktop' => [
                                'page' => 'https://en.wikipedia.org/wiki/Bridge',
                            ],
                        ],
                    ]],
                ],
            ],
            'births' => [],
            'deaths' => [],
            'holidays' => [],
        ];
    }

    private function wikiEndpointForDate(string $baseUrl, Carbon $date): string
    {
        return sprintf('%s/%02d/%02d', rtrim($baseUrl, '/'), $date->month, $date->day);
    }

    /**
     * @param array<string,mixed> $overrides
     * @return array<string,mixed>
     */
    private function apodPayload(array $overrides = []): array
    {
        return array_replace([
            'date' => '2026-02-20',
            'title' => 'APOD Test Title',
            'explanation' => 'APOD explanation text long enough to pass content checks for publishing.',
            'url' => 'https://apod.nasa.gov/apod/image/test.jpg',
            'hdurl' => 'https://apod.nasa.gov/apod/image/test-hd.jpg',
            'media_type' => 'image',
            'copyright' => 'NASA/ESA',
        ], $overrides);
    }

    private function imageFixtureBinary(): string
    {
        return (string) file_get_contents(base_path('tests/Fixtures/images/large-sample.jpg'));
    }

    private function rssWithMissingTitleAndLink(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <title>NASA News Releases</title>
    <item>
      <guid isPermaLink="false">guid-missing-title-and-link</guid>
      <pubDate>Wed, 18 Feb 2026 11:00:00 GMT</pubDate>
      <description><![CDATA[<p>Body exists but title and link are missing.</p>]]></description>
    </item>
  </channel>
</rss>
XML;
    }

    private function rssForGuid(string $guid): string
    {
        return sprintf(
            <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <title>NASA News Releases</title>
    <item>
      <title>Existing linked item</title>
      <link>https://www.nasa.gov/news-release/prelinked/</link>
      <guid isPermaLink="false">%s</guid>
      <pubDate>Thu, 19 Feb 2026 08:00:00 GMT</pubDate>
      <description><![CDATA[<p>Body text with enough length to pass content checks.</p>]]></description>
    </item>
  </channel>
</rss>
XML,
            $guid
        );
    }

    private function rssSingleItem(string $guid, string $title, string $link, string $description): string
    {
        return sprintf(
            <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <title>NASA News Releases</title>
    <item>
      <title>%s</title>
      <link>%s</link>
      <guid isPermaLink="false">%s</guid>
      <pubDate>Thu, 19 Feb 2026 08:00:00 GMT</pubDate>
      <description><![CDATA[<p>%s</p>]]></description>
    </item>
  </channel>
</rss>
XML,
            htmlspecialchars($title, ENT_QUOTES | ENT_XML1),
            htmlspecialchars($link, ENT_QUOTES | ENT_XML1),
            htmlspecialchars($guid, ENT_QUOTES | ENT_XML1),
            htmlspecialchars($description, ENT_QUOTES | ENT_XML1)
        );
    }

    private function createExistingBotPost(): Post
    {
        $user = User::factory()->create([
            'is_bot' => true,
            'username' => 'kozmo',
            'email' => 'kozmo-existing@astrokomunita.local',
        ]);

        return Post::query()->create([
            'user_id' => $user->id,
            'feed_key' => 'astro',
            'author_kind' => 'bot',
            'bot_identity' => 'kozmo',
            'content' => 'Already published bot post',
            'source_name' => 'bot_nasa_rss_breaking',
            'source_uid' => sha1('nasa_rss_breaking|nasa-guid-existing'),
            'source_url' => 'https://www.nasa.gov/news-release/prelinked/',
            'source_published_at' => now(),
            'is_hidden' => false,
            'moderation_status' => 'ok',
        ]);
    }
}
