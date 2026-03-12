<?php

namespace Tests\Feature\Bots;

use App\Models\BotItem;
use App\Models\BotRun;
use App\Models\Post;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

class RunBotSourceCommandWikipediaAndTranslationTest extends RunBotSourceCommandTestCase
{
    public function test_wikipedia_relevant_events_publish_single_kozmo_post(): void
    {
        config()->set('bots.translation.primary', 'libretranslate');
        config()->set('bots.translation.fallback', 'none');
        config()->set('bots.translation.timeout_sec', 5);
        config()->set('bots.translation.libretranslate.url', 'http://translation.test');

        $this->travelTo(Carbon::parse('2026-02-20 10:00:00'));
        try {
            $source = $this->createWikipediaSource();
            Http::fake([
                $this->wikiEndpointForDate($source->url, now()) => Http::response(
                    $this->wikiFixturePayload(),
                    200,
                    ['Content-Type' => 'application/json']
                ),
                'http://translation.test/*' => function ($request) {
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
        config()->set('bots.translation.primary', 'dummy');

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
        config()->set('bots.translation.primary', 'dummy');

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
        $this->assertDatabaseHas('bot_activity_logs', [
            'action' => 'publish',
            'outcome' => 'skipped',
            'reason' => 'missing_title_or_url',
            'bot_item_id' => $item->id,
        ]);

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
        config()->set('bots.translation.primary', 'libretranslate');
        config()->set('bots.translation.fallback', 'none');
        config()->set('bots.translation.timeout_sec', 5);
        config()->set('bots.translation.libretranslate.url', 'http://translation.test');

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
            'http://translation.test/*' => function ($request) {
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
        config()->set('bots.translation.primary', 'libretranslate');
        config()->set('bots.translation.fallback', 'none');
        config()->set('bots.translation.timeout_sec', 5);
        config()->set('bots.translation.libretranslate.url', 'http://translation.test');

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
            'http://translation.test/*' => Http::response([
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
        config()->set('bots.translation.primary', 'libretranslate');
        config()->set('bots.translation.fallback', 'none');
        config()->set('bots.translation.timeout_sec', 5);
        config()->set('bots.translation.libretranslate.url', 'http://translation.test');

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
            'http://translation.test/*' => function ($request) use (&$translationCalls) {
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
        config()->set('bots.translation.primary', 'libretranslate');
        config()->set('bots.translation.fallback', 'none');
        config()->set('bots.translation.timeout_sec', 5);
        config()->set('bots.translation.libretranslate.url', 'http://translation.test');

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
            'http://translation.test/*' => function ($request) {
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
        config()->set('bots.translation.primary', 'libretranslate');
        config()->set('bots.translation.fallback', 'none');
        config()->set('bots.translation.timeout_sec', 5);
        config()->set('bots.translation.libretranslate.url', 'http://translation.test');

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
            'http://translation.test/*' => function ($request) {
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

}
