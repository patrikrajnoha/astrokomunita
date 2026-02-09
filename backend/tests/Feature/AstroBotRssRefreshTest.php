<?php

namespace Tests\Feature;

use App\Models\RssItem;
use App\Models\Post;
use App\Models\User;
use App\Services\AstroBotRssRefreshService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AstroBotRssRefreshTest extends TestCase
{
    use RefreshDatabase;

    public function test_refresh_command_inserts_new_items_and_is_idempotent(): void
    {
        config()->set('astrobot.rss_retention_days', 0);
        config()->set('astrobot.rss_retention_max_items', 0);

        Http::fake([
            '*' => Http::response($this->rssPayload([
                ['guid' => 'guid-1', 'url' => 'https://example.test/a', 'title' => 'Alpha', 'summary' => 'A'],
                ['guid' => 'guid-2', 'url' => 'https://example.test/b', 'title' => 'Beta', 'summary' => 'B'],
            ]), 200),
        ]);

        Artisan::call('astrobot:rss:refresh');
        $this->assertDatabaseCount('rss_items', 2);

        Artisan::call('astrobot:rss:refresh');
        $this->assertDatabaseCount('rss_items', 2);
    }

    public function test_cleanup_removes_old_and_excess_non_published_items(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-09 12:00:00'));
        config()->set('astrobot.rss_retention_days', 30);
        config()->set('astrobot.rss_retention_max_items', 2);

        RssItem::create([
            'source' => 'nasa_news',
            'guid' => 'old-pending',
            'url' => 'https://example.test/old-pending',
            'dedupe_hash' => sha1('old-pending'),
            'title' => 'Old pending',
            'summary' => 'Old pending',
            'fetched_at' => now()->subDays(40),
            'status' => RssItem::STATUS_PENDING,
            'created_at' => now()->subDays(40),
            'updated_at' => now()->subDays(40),
        ]);

        $postUser = User::factory()->create();
        $publishedPost = Post::query()->create([
            'user_id' => $postUser->id,
            'content' => 'published post',
        ]);

        RssItem::create([
            'source' => 'nasa_news',
            'guid' => 'old-published',
            'url' => 'https://example.test/old-published',
            'dedupe_hash' => sha1('old-published'),
            'title' => 'Old published',
            'summary' => 'Old published',
            'fetched_at' => now()->subDays(40),
            'status' => RssItem::STATUS_PUBLISHED,
            'post_id' => $publishedPost->id,
            'created_at' => now()->subDays(40),
            'updated_at' => now()->subDays(40),
        ]);

        foreach (['new-1', 'new-2', 'new-3'] as $key) {
            RssItem::create([
                'source' => 'nasa_news',
                'guid' => $key,
                'url' => "https://example.test/{$key}",
                'dedupe_hash' => sha1($key),
                'title' => $key,
                'summary' => $key,
                'fetched_at' => now(),
                'status' => RssItem::STATUS_PENDING,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        app(AstroBotRssRefreshService::class)->cleanupNonPublished();

        $this->assertDatabaseHas('rss_items', ['guid' => 'old-published']);
        $this->assertDatabaseMissing('rss_items', ['guid' => 'old-pending']);

        $remainingNonPublished = RssItem::query()
            ->where('status', '!=', RssItem::STATUS_PUBLISHED)
            ->count();
        $this->assertSame(2, $remainingNonPublished);
    }

    public function test_admin_rss_refresh_endpoint_requires_auth_and_admin_and_adds_items(): void
    {
        Http::fake([
            '*' => Http::response($this->rssPayload([
                ['guid' => 'guid-admin-refresh', 'url' => 'https://example.test/admin', 'title' => 'Admin refresh', 'summary' => 'admin'],
            ]), 200),
        ]);

        $this->postJson('/api/admin/astrobot/rss/refresh')->assertStatus(401);

        $admin = User::factory()->create([
            'is_admin' => true,
            'role' => 'admin',
            'is_active' => true,
        ]);
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/admin/astrobot/rss/refresh');
        $response->assertOk();
        $response->assertJsonPath('result.created', 1);

        $this->assertDatabaseHas('rss_items', [
            'guid' => 'guid-admin-refresh',
            'status' => RssItem::STATUS_PENDING,
        ]);
    }

    public function test_admin_can_approve_pending_item_via_item_route_binding(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
            'role' => 'admin',
            'is_active' => true,
        ]);
        Sanctum::actingAs($admin);

        $item = RssItem::create([
            'source' => 'nasa_news',
            'guid' => 'guid-approve',
            'url' => 'https://example.test/approve',
            'dedupe_hash' => sha1('guid-approve'),
            'title' => 'Approve me',
            'summary' => 'Approve me',
            'fetched_at' => now(),
            'status' => RssItem::STATUS_PENDING,
        ]);

        $this->postJson("/api/admin/astrobot/items/{$item->id}/approve")
            ->assertOk()
            ->assertJsonPath('message', 'Item approved.');

        $this->assertDatabaseHas('rss_items', [
            'id' => $item->id,
            'status' => RssItem::STATUS_APPROVED,
        ]);
    }

    /**
     * @param array<int,array{guid:string,url:string,title:string,summary:string}> $rows
     */
    private function rssPayload(array $rows): string
    {
        $items = '';
        foreach ($rows as $row) {
            $items .= sprintf(
                '<item><guid>%s</guid><title>%s</title><link>%s</link><description>%s</description><pubDate>Mon, 09 Feb 2026 12:00:00 GMT</pubDate></item>',
                htmlspecialchars($row['guid'], ENT_QUOTES),
                htmlspecialchars($row['title'], ENT_QUOTES),
                htmlspecialchars($row['url'], ENT_QUOTES),
                htmlspecialchars($row['summary'], ENT_QUOTES)
            );
        }

        return <<<XML
<?xml version="1.0" encoding="UTF-8" ?>
<rss version="2.0">
  <channel>
    <title>NASA Test Feed</title>
    {$items}
  </channel>
</rss>
XML;
    }
}
