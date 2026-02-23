<?php

namespace Tests\Unit;

use App\Enums\BotSourceType;
use App\Models\BotItem;
use App\Models\BotRun;
use App\Models\BotSource;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class BotsPurgeCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_purge_command_deletes_old_runs_and_orphan_items_only(): void
    {
        $source = $this->createSource();

        $oldRun = BotRun::query()->create([
            'bot_identity' => 'kozmo',
            'source_id' => $source->id,
            'started_at' => now()->subDays(120),
            'finished_at' => now()->subDays(120)->addMinute(),
            'status' => 'success',
            'stats' => [],
            'error_text' => null,
        ]);
        $recentRun = BotRun::query()->create([
            'bot_identity' => 'kozmo',
            'source_id' => $source->id,
            'started_at' => now()->subDays(10),
            'finished_at' => now()->subDays(10)->addMinute(),
            'status' => 'success',
            'stats' => [],
            'error_text' => null,
        ]);

        $publishedPost = Post::factory()->create([
            'feed_key' => 'astro',
            'author_kind' => 'bot',
            'bot_identity' => 'kozmo',
            'source_name' => 'bot_test',
            'source_uid' => sha1('bot_test'),
        ]);

        $oldOrphan = $this->createItem($source, 'old-orphan', now()->subDays(200), 'pending', null);
        $oldFailedOrphan = $this->createItem($source, 'old-failed-orphan', now()->subDays(40), 'failed', null);
        $oldPublished = $this->createItem($source, 'old-published', now()->subDays(220), 'published', $publishedPost->id);
        $recentOrphan = $this->createItem($source, 'recent-orphan', now()->subDays(5), 'failed', null);

        $exitCode = Artisan::call('bots:purge', [
            '--runs-days' => 90,
            '--items-days' => 180,
            '--orphan-days' => 30,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertDatabaseMissing('bot_runs', ['id' => $oldRun->id]);
        $this->assertDatabaseHas('bot_runs', ['id' => $recentRun->id]);

        $this->assertDatabaseMissing('bot_items', ['id' => $oldOrphan->id]);
        $this->assertDatabaseMissing('bot_items', ['id' => $oldFailedOrphan->id]);
        $this->assertDatabaseHas('bot_items', ['id' => $oldPublished->id]);
        $this->assertDatabaseHas('bot_items', ['id' => $recentOrphan->id]);
    }

    public function test_purge_command_dry_run_keeps_data_untouched(): void
    {
        $source = $this->createSource();
        $run = BotRun::query()->create([
            'bot_identity' => 'kozmo',
            'source_id' => $source->id,
            'started_at' => now()->subDays(365),
            'finished_at' => now()->subDays(365)->addMinute(),
            'status' => 'success',
            'stats' => [],
            'error_text' => null,
        ]);
        $item = $this->createItem($source, 'dry-run-item', now()->subDays(365), 'failed', null);

        $exitCode = Artisan::call('bots:purge', [
            '--runs-days' => 30,
            '--items-days' => 30,
            '--dry-run' => true,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertDatabaseHas('bot_runs', ['id' => $run->id]);
        $this->assertDatabaseHas('bot_items', ['id' => $item->id]);
    }

    private function createSource(): BotSource
    {
        return BotSource::query()->create([
            'key' => 'purge_source',
            'bot_identity' => 'kozmo',
            'source_type' => BotSourceType::RSS->value,
            'url' => 'https://example.test/rss.xml',
            'is_enabled' => true,
            'schedule' => null,
        ]);
    }

    private function createItem(BotSource $source, string $stableKey, \DateTimeInterface $fetchedAt, string $status, ?int $postId): BotItem
    {
        return BotItem::query()->create([
            'bot_identity' => 'kozmo',
            'source_id' => $source->id,
            'post_id' => $postId,
            'stable_key' => $stableKey,
            'title' => 'Retention item',
            'summary' => 'Body text long enough for bot item.',
            'content' => 'Body text long enough for bot item.',
            'url' => 'https://example.test/' . $stableKey,
            'published_at' => null,
            'fetched_at' => $fetchedAt,
            'lang_original' => 'en',
            'lang_detected' => null,
            'title_translated' => null,
            'content_translated' => null,
            'translation_status' => 'pending',
            'publish_status' => $status,
            'meta' => null,
        ]);
    }
}
