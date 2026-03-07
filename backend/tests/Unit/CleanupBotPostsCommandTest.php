<?php

namespace Tests\Unit;

use App\Enums\BotSourceType;
use App\Models\AppSetting;
use App\Models\BotItem;
use App\Models\BotSource;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class CleanupBotPostsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_cleanup_command_skips_when_retention_is_disabled(): void
    {
        $source = $this->createSource();
        $botUser = $this->createBotUser();
        $oldPost = $this->createBotPost($botUser, $source, 'skip-old', now()->subHours(72));
        $this->createItem($source, 'skip-item', $oldPost->id, now()->subHours(72));

        AppSetting::put('bots.posts.auto_delete_enabled', '0');
        AppSetting::put('bots.posts.auto_delete_after_hours', '24');

        $exitCode = Artisan::call('bots:posts:cleanup', [
            '--limit' => 200,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertDatabaseHas('posts', ['id' => $oldPost->id]);
    }

    public function test_cleanup_command_deletes_old_bot_posts_when_retention_is_enabled(): void
    {
        $source = $this->createSource();
        $botUser = $this->createBotUser();

        $oldPost = $this->createBotPost($botUser, $source, 'cleanup-old', now()->subHours(60));
        $freshPost = $this->createBotPost($botUser, $source, 'cleanup-fresh', now()->subHours(2));

        $oldItem = $this->createItem($source, 'cleanup-item-old', $oldPost->id, now()->subHours(60));
        $freshItem = $this->createItem($source, 'cleanup-item-fresh', $freshPost->id, now()->subHours(2));

        AppSetting::put('bots.posts.auto_delete_enabled', '1');
        AppSetting::put('bots.posts.auto_delete_after_hours', '24');

        $exitCode = Artisan::call('bots:posts:cleanup', [
            '--limit' => 200,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertDatabaseMissing('posts', ['id' => $oldPost->id]);
        $this->assertDatabaseHas('posts', ['id' => $freshPost->id]);

        $oldItem->refresh();
        $freshItem->refresh();

        $this->assertNull($oldItem->post_id);
        $this->assertSame('pending', (string) ($oldItem->publish_status?->value ?? $oldItem->publish_status));
        $this->assertTrue((bool) data_get($oldItem->meta, 'deleted_by_retention'));

        $this->assertSame($freshPost->id, (int) $freshItem->post_id);
    }

    private function createSource(): BotSource
    {
        return BotSource::query()->create([
            'key' => 'retention_command_source',
            'bot_identity' => 'kozmo',
            'source_type' => BotSourceType::RSS->value,
            'url' => 'https://example.test/rss.xml',
            'is_enabled' => true,
            'schedule' => null,
        ]);
    }

    private function createBotUser(): User
    {
        return User::factory()->create([
            'is_bot' => true,
            'role' => User::ROLE_BOT,
            'username' => 'cleanupbot',
            'email' => null,
        ]);
    }

    private function createBotPost(User $botUser, BotSource $source, string $suffix, \DateTimeInterface $createdAt): Post
    {
        $post = Post::query()->create([
            'user_id' => $botUser->id,
            'feed_key' => 'astro',
            'author_kind' => 'bot',
            'bot_identity' => 'kozmo',
            'content' => 'Bot post ' . $suffix,
            'source_name' => 'bot_' . $source->key,
            'source_uid' => sha1($suffix),
            'moderation_status' => 'ok',
        ]);

        $post->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->save();

        return $post;
    }

    private function createItem(BotSource $source, string $stableKey, int $postId, \DateTimeInterface $fetchedAt): BotItem
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
            'translation_status' => 'done',
            'publish_status' => 'published',
            'meta' => null,
        ]);
    }
}

