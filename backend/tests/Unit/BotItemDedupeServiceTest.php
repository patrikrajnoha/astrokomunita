<?php

namespace Tests\Unit;

use App\Enums\BotPublishStatus;
use App\Enums\BotSourceType;
use App\Enums\BotTranslationStatus;
use App\Models\BotItem;
use App\Models\BotRun;
use App\Models\BotSource;
use App\Models\Post;
use App\Models\User;
use App\Services\Bots\BotItemDedupeService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BotItemDedupeServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_upsert_by_stable_key_does_not_create_duplicates(): void
    {
        $source = BotSource::query()->create([
            'key' => 'test.nasa.rss',
            'bot_identity' => 'stela',
            'source_type' => BotSourceType::RSS->value,
            'url' => 'https://example.test/feed.xml',
            'is_enabled' => true,
            'schedule' => ['cron' => '*/30 * * * *'],
        ]);

        $service = app(BotItemDedupeService::class);

        $first = $service->upsertByStableKey($source, 'same-key', [
            'title' => 'First title',
            'summary' => 'First summary',
            'translation_status' => BotTranslationStatus::PENDING->value,
            'publish_status' => BotPublishStatus::PENDING->value,
        ]);

        $second = $service->upsertByStableKey($source, 'same-key', [
            'title' => 'Updated title',
            'summary' => 'Updated summary',
            'translation_status' => BotTranslationStatus::DONE->value,
            'publish_status' => BotPublishStatus::SKIPPED->value,
        ]);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, BotItem::query()->count());
        $this->assertDatabaseHas('bot_items', [
            'id' => $first->id,
            'source_id' => $source->id,
            'stable_key' => 'same-key',
            'title' => 'Updated title',
            'translation_status' => BotTranslationStatus::DONE->value,
            'publish_status' => BotPublishStatus::SKIPPED->value,
        ]);
    }

    public function test_upsert_assigns_run_id_for_new_item_and_tracks_last_seen_run_for_dupe(): void
    {
        $source = BotSource::query()->create([
            'key' => 'test.wikipedia.daily',
            'bot_identity' => 'kozmo',
            'source_type' => BotSourceType::WIKIPEDIA->value,
            'url' => 'https://example.test/wiki',
            'is_enabled' => true,
            'schedule' => null,
        ]);

        $service = app(BotItemDedupeService::class);
        $firstRun = BotRun::query()->create([
            'bot_identity' => 'kozmo',
            'source_id' => $source->id,
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
            'status' => 'success',
            'stats' => ['fetched_count' => 1],
            'meta' => ['run_context' => 'admin'],
        ]);
        $secondRun = BotRun::query()->create([
            'bot_identity' => 'kozmo',
            'source_id' => $source->id,
            'started_at' => now(),
            'finished_at' => now()->addMinute(),
            'status' => 'success',
            'stats' => ['fetched_count' => 1],
            'meta' => ['run_context' => 'admin'],
        ]);

        $created = $service->upsertByStableKey($source, 'stable-1', [
            'title' => 'First',
            'summary' => 'First summary',
        ], $firstRun->id);

        $this->assertSame($firstRun->id, $created->run_id);

        $updated = $service->upsertByStableKey($source, 'stable-1', [
            'title' => 'Second',
            'summary' => 'Second summary',
        ], $secondRun->id);

        $this->assertSame($created->id, $updated->id);
        $this->assertSame($firstRun->id, $updated->run_id);
        $this->assertSame($secondRun->id, (int) data_get($updated->meta, 'last_seen_run_id'));
        $this->assertNotSame('', (string) data_get($updated->meta, 'seen_at'));
    }

    public function test_posts_have_unique_bot_item_id_for_dedupe_safety(): void
    {
        $source = BotSource::query()->create([
            'key' => 'test.unique.post.link',
            'bot_identity' => 'kozmo',
            'source_type' => BotSourceType::RSS->value,
            'url' => 'https://example.test/feed.xml',
            'is_enabled' => true,
        ]);
        $item = BotItem::query()->create([
            'bot_identity' => 'kozmo',
            'source_id' => $source->id,
            'stable_key' => 'unique-link',
            'title' => 'Unique item',
            'publish_status' => BotPublishStatus::PENDING->value,
            'translation_status' => BotTranslationStatus::PENDING->value,
            'fetched_at' => now(),
        ]);
        $botUser = User::factory()->create([
            'is_bot' => true,
            'role' => User::ROLE_BOT,
            'username' => 'uniquebot',
            'email' => null,
        ]);

        Post::query()->create([
            'user_id' => $botUser->id,
            'feed_key' => 'astro',
            'author_kind' => 'bot',
            'bot_identity' => 'kozmo',
            'content' => 'First bot post',
            'source_name' => 'bot_test',
            'source_uid' => sha1('first'),
            'bot_item_id' => $item->id,
        ]);

        $this->expectException(QueryException::class);

        Post::query()->create([
            'user_id' => $botUser->id,
            'feed_key' => 'astro',
            'author_kind' => 'bot',
            'bot_identity' => 'kozmo',
            'content' => 'Second bot post',
            'source_name' => 'bot_test',
            'source_uid' => sha1('second'),
            'bot_item_id' => $item->id,
        ]);
    }
}
