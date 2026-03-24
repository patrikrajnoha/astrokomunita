<?php

namespace Tests\Unit;

use App\Enums\BotSourceType;
use App\Models\BotItem;
use App\Models\BotSource;
use App\Models\Post;
use App\Services\Bots\BotPostTranslationBackfillService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Mockery;
use Tests\TestCase;

class BackfillBotTranslationsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_dry_run_reports_eligible_items_without_writing(): void
    {
        $source = $this->createSource('dry_source');
        $post = Post::factory()->create();
        $linkedItem = $this->createLinkedItem($source, $post->id, 'dry-linked-item');
        $this->createUnlinkedItem($source, 'dry-unlinked-item');

        $mock = Mockery::mock(BotPostTranslationBackfillService::class);
        $mock->shouldReceive('backfill')->never();
        $this->app->instance(BotPostTranslationBackfillService::class, $mock);

        $exitCode = Artisan::call('bots:backfill-translations', [
            '--source' => $source->key,
            '--limit' => 10,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Dry-run summary', Artisan::output());
        $this->assertDatabaseHas('bot_items', [
            'id' => $linkedItem->id,
            'translation_status' => 'pending',
            'title_translated' => null,
            'content_translated' => null,
        ]);
    }

    public function test_command_apply_calls_backfill_service_and_succeeds(): void
    {
        $source = $this->createSource('apply_source');

        $mock = Mockery::mock(BotPostTranslationBackfillService::class);
        $mock->shouldReceive('backfill')
            ->once()
            ->withArgs(function (BotSource $resolvedSource, int $limit, ?int $runId, bool $force) use ($source): bool {
                return (int) $resolvedSource->id === (int) $source->id
                    && $limit === 5
                    && $runId === 987
                    && $force === false;
            })
            ->andReturn([
                'source_key' => $source->key,
                'run_id' => 987,
                'force' => false,
                'limit' => 5,
                'scanned' => 1,
                'updated_posts' => 1,
                'skipped' => 0,
                'failed' => 0,
                'failures' => [],
            ]);
        $this->app->instance(BotPostTranslationBackfillService::class, $mock);

        $exitCode = Artisan::call('bots:backfill-translations', [
            '--source' => $source->key,
            '--limit' => 5,
            '--run-id' => 987,
            '--apply' => true,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('updated_posts=1', Artisan::output());
    }

    public function test_command_fails_for_missing_source(): void
    {
        $exitCode = Artisan::call('bots:backfill-translations', [
            '--source' => 'missing_source',
            '--apply' => true,
        ]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('was not found', Artisan::output());
    }

    private function createSource(string $key): BotSource
    {
        return BotSource::query()->create([
            'key' => strtolower(trim($key)),
            'name' => 'Backfill source',
            'bot_identity' => 'kozmo',
            'source_type' => BotSourceType::RSS->value,
            'url' => 'https://example.test/' . $key . '.xml',
            'is_enabled' => true,
        ]);
    }

    private function createLinkedItem(BotSource $source, int $postId, string $stableKey): BotItem
    {
        return BotItem::query()->create([
            'bot_identity' => 'kozmo',
            'source_id' => $source->id,
            'run_id' => null,
            'post_id' => $postId,
            'stable_key' => $stableKey,
            'title' => 'English title',
            'summary' => 'English summary.',
            'content' => 'English content used for translation backfill test.',
            'url' => 'https://example.test/items/' . $stableKey,
            'published_at' => now()->subHour(),
            'fetched_at' => now()->subHour(),
            'lang_original' => 'en',
            'lang_detected' => 'en',
            'title_translated' => null,
            'content_translated' => null,
            'translation_status' => 'pending',
            'translation_error' => null,
            'translation_provider' => null,
            'translated_at' => null,
            'publish_status' => 'published',
            'meta' => [],
        ]);
    }

    private function createUnlinkedItem(BotSource $source, string $stableKey): BotItem
    {
        return BotItem::query()->create([
            'bot_identity' => 'kozmo',
            'source_id' => $source->id,
            'run_id' => null,
            'post_id' => null,
            'stable_key' => $stableKey,
            'title' => 'English title',
            'summary' => 'English summary.',
            'content' => 'English content not linked to post.',
            'url' => 'https://example.test/items/' . $stableKey,
            'published_at' => now()->subHour(),
            'fetched_at' => now()->subHour(),
            'lang_original' => 'en',
            'lang_detected' => 'en',
            'title_translated' => null,
            'content_translated' => null,
            'translation_status' => 'pending',
            'translation_error' => null,
            'translation_provider' => null,
            'translated_at' => null,
            'publish_status' => 'published',
            'meta' => [],
        ]);
    }
}
