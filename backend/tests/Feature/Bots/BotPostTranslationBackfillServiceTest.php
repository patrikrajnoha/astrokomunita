<?php

namespace Tests\Feature\Bots;

use App\Enums\BotSourceType;
use App\Models\BotItem;
use App\Models\BotSource;
use App\Models\Post;
use App\Models\User;
use App\Services\Bots\BotPostTranslationBackfillService;
use App\Services\Bots\Contracts\BotTranslationServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BotPostTranslationBackfillServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('moderation.enabled', false);
    }

    public function test_backfill_updates_existing_post_and_keeps_post_count(): void
    {
        $source = $this->createSource();
        $post = $this->createEnglishBotPost();

        $item = BotItem::query()->create([
            'bot_identity' => 'kozmo',
            'source_id' => $source->id,
            'run_id' => null,
            'post_id' => $post->id,
            'stable_key' => 'guid-backfill-1',
            'title' => 'English legacy title',
            'summary' => 'English legacy body text long enough for publish.',
            'content' => 'English legacy body text long enough for publish.',
            'url' => 'https://www.nasa.gov/news-release/backfill-1/',
            'published_at' => now(),
            'fetched_at' => now(),
            'lang_original' => 'en',
            'translation_status' => 'pending',
            'publish_status' => 'published',
            'meta' => [],
        ]);

        $this->mockTranslator();
        $service = app(BotPostTranslationBackfillService::class);

        $result = $service->backfill($source, 10, null);

        $item->refresh();
        $post->refresh();

        $this->assertSame(1, (int) $result['scanned']);
        $this->assertSame(1, (int) $result['updated_posts']);
        $this->assertSame(0, (int) $result['failed']);
        $this->assertSame(1, Post::query()->count());

        $this->assertSame('done', (string) $item->translation_status->value);
        $this->assertSame('mock', (string) $item->translation_provider);
        $this->assertNotNull($item->translated_at);
        $this->assertStringContainsString('SK English legacy title', (string) $post->content);
        $this->assertSame('done', (string) $post->translation_status);
    }

    public function test_backfill_is_idempotent_on_second_run(): void
    {
        $source = $this->createSource();
        $post = $this->createEnglishBotPost();

        BotItem::query()->create([
            'bot_identity' => 'kozmo',
            'source_id' => $source->id,
            'run_id' => null,
            'post_id' => $post->id,
            'stable_key' => 'guid-backfill-2',
            'title' => 'English idempotent title',
            'summary' => 'English idempotent body text long enough for publish.',
            'content' => 'English idempotent body text long enough for publish.',
            'url' => 'https://www.nasa.gov/news-release/backfill-2/',
            'published_at' => now(),
            'fetched_at' => now(),
            'lang_original' => 'en',
            'translation_status' => 'pending',
            'publish_status' => 'published',
            'meta' => [],
        ]);

        $this->mockTranslator();
        $service = app(BotPostTranslationBackfillService::class);

        $first = $service->backfill($source, 10, null);
        $second = $service->backfill($source, 10, null);

        $this->assertSame(1, (int) $first['updated_posts']);
        $this->assertSame(0, (int) $second['updated_posts']);
        $this->assertGreaterThanOrEqual(1, (int) $second['skipped']);
        $this->assertSame(1, Post::query()->count());
    }

    public function test_backfill_force_retranslates_done_items(): void
    {
        $source = $this->createSource();
        $post = $this->createEnglishBotPost();
        $post->forceFill([
            'content' => 'OLD TITLE' . "\n\n" . 'OLD CONTENT',
            'translation_status' => 'done',
        ])->save();

        $item = BotItem::query()->create([
            'bot_identity' => 'kozmo',
            'source_id' => $source->id,
            'run_id' => null,
            'post_id' => $post->id,
            'stable_key' => 'guid-backfill-force-1',
            'title' => 'English force title',
            'summary' => 'English force body text long enough for publish.',
            'content' => 'English force body text long enough for publish.',
            'url' => 'https://www.nasa.gov/news-release/backfill-force-1/',
            'published_at' => now(),
            'fetched_at' => now(),
            'lang_original' => 'en',
            'title_translated' => 'OLD TITLE',
            'content_translated' => 'OLD CONTENT',
            'translation_status' => 'done',
            'translation_provider' => 'legacy',
            'publish_status' => 'published',
            'meta' => [],
        ]);

        $this->mockTranslator();
        $service = app(BotPostTranslationBackfillService::class);

        $withoutForce = $service->backfill($source, 10, null, false);
        $item->refresh();
        $this->assertSame('OLD TITLE', (string) $item->title_translated);
        $this->assertSame('legacy', (string) $item->translation_provider);
        $this->assertNull($item->translated_at);

        $withForce = $service->backfill($source, 10, null, true);

        $item->refresh();
        $post->refresh();

        $this->assertSame(1, (int) $withForce['updated_posts']);
        $this->assertSame('SK English force title', (string) $item->title_translated);
        $this->assertSame('mock', (string) $item->translation_provider);
        $this->assertStringContainsString('SK English force body text long enough for publish.', (string) $post->content);
    }

    public function test_backfill_uses_legacy_meta_post_id_when_post_id_column_is_null(): void
    {
        $source = $this->createSource();
        $post = $this->createEnglishBotPost();

        $item = BotItem::query()->create([
            'bot_identity' => 'kozmo',
            'source_id' => $source->id,
            'run_id' => null,
            'post_id' => null,
            'stable_key' => 'guid-backfill-legacy-meta-post-id',
            'title' => 'English legacy meta post id title',
            'summary' => 'English legacy meta post id body text long enough for publish.',
            'content' => 'English legacy meta post id body text long enough for publish.',
            'url' => 'https://www.nasa.gov/news-release/backfill-meta-post-id/',
            'published_at' => now(),
            'fetched_at' => now(),
            'lang_original' => 'en',
            'translation_status' => 'pending',
            'publish_status' => 'published',
            'meta' => [
                'post_id' => $post->id,
            ],
        ]);

        $this->mockTranslator();
        $service = app(BotPostTranslationBackfillService::class);

        $result = $service->backfill($source, 10, null);

        $item->refresh();
        $post->refresh();

        $this->assertSame(1, (int) $result['scanned']);
        $this->assertSame(1, (int) $result['updated_posts']);
        $this->assertSame($post->id, (int) $item->post_id);
        $this->assertStringContainsString('SK English legacy meta post id title', (string) $post->content);
    }

    public function test_force_backfill_restores_previous_translation_when_new_translation_is_empty(): void
    {
        $source = $this->createSource();
        $post = $this->createEnglishBotPost();
        $post->forceFill([
            'content' => 'OLD TITLE' . "\n\n" . 'OLD CONTENT',
            'translation_status' => 'done',
        ])->save();

        $item = BotItem::query()->create([
            'bot_identity' => 'kozmo',
            'source_id' => $source->id,
            'run_id' => null,
            'post_id' => $post->id,
            'stable_key' => 'guid-backfill-force-empty',
            'title' => 'English fallback title',
            'summary' => 'English fallback body text long enough for publish.',
            'content' => 'English fallback body text long enough for publish.',
            'url' => 'https://www.nasa.gov/news-release/backfill-force-empty/',
            'published_at' => now(),
            'fetched_at' => now(),
            'lang_original' => 'en',
            'title_translated' => 'OLD TITLE',
            'content_translated' => 'OLD CONTENT',
            'translation_status' => 'done',
            'translation_provider' => 'legacy',
            'translation_error' => null,
            'publish_status' => 'published',
            'meta' => [],
        ]);

        $this->mockFailingTranslator();
        $service = app(BotPostTranslationBackfillService::class);

        $result = $service->backfill($source, 10, null, true);

        $item->refresh();

        $this->assertSame(0, (int) $result['failed']);
        $this->assertSame('OLD TITLE', (string) $item->title_translated);
        $this->assertSame('OLD CONTENT', (string) $item->content_translated);
        $this->assertSame('legacy', (string) $item->translation_provider);
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

    private function createEnglishBotPost(): Post
    {
        $botUser = User::factory()->create([
            'is_bot' => true,
            'username' => 'kozmobot',
            'name' => 'Kozmo',
            'email' => 'kozmobot@astrokomunita.local',
        ]);

        return Post::query()->create([
            'user_id' => $botUser->id,
            'feed_key' => 'astro',
            'author_kind' => 'bot',
            'bot_identity' => 'kozmo',
            'content' => 'NASA | English legacy title' . "\n\n" . 'English legacy body text long enough for publish.',
            'source_name' => 'bot_nasa_rss_breaking',
            'source_uid' => sha1('nasa_rss_breaking|legacy-backfill-post'),
            'source_url' => 'https://www.nasa.gov/news-release/backfill/',
            'translation_status' => 'pending',
            'meta' => [],
        ]);
    }

    private function mockTranslator(): void
    {
        $this->app->instance(BotTranslationServiceInterface::class, new class implements BotTranslationServiceInterface {
            public function translate(?string $title, ?string $content, string $to = 'sk'): array
            {
                return [
                    'translated_title' => 'SK ' . trim((string) $title),
                    'translated_content' => 'SK ' . trim((string) $content),
                    'title_translated' => 'SK ' . trim((string) $title),
                    'content_translated' => 'SK ' . trim((string) $content),
                    'status' => 'done',
                    'meta' => [
                        'provider' => 'mock',
                        'target_lang' => $to,
                        'duration_ms' => 12,
                        'chars' => strlen(trim((string) $title) . trim((string) $content)),
                        'error' => null,
                        'translated_at' => now()->toIso8601String(),
                    ],
                ];
            }
        });
    }

    private function mockFailingTranslator(): void
    {
        $this->app->instance(BotTranslationServiceInterface::class, new class implements BotTranslationServiceInterface {
            public function translate(?string $title, ?string $content, string $to = 'sk'): array
            {
                return [
                    'translated_title' => null,
                    'translated_content' => null,
                    'title_translated' => null,
                    'content_translated' => null,
                    'status' => 'failed',
                    'meta' => [
                        'provider' => 'mock',
                        'target_lang' => $to,
                        'duration_ms' => 12,
                        'chars' => strlen(trim((string) $title) . trim((string) $content)),
                        'error' => 'quality_guard_failed:contains_en_connectors',
                        'translated_at' => now()->toIso8601String(),
                    ],
                ];
            }
        });
    }
}
