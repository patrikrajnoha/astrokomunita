<?php

namespace Tests\Feature;

use App\Models\RssItem;
use App\Models\User;
use App\Services\AstroBotRssService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AstroBotRssServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_sync_is_idempotent_and_deletes_items_missing_from_feed(): void
    {
        config()->set('astrobot.rss_url', 'https://example.test/rss');
        config()->set('astrobot.max_items_per_sync', 80);
        config()->set('astrobot.max_age_days', 30);

        Http::fakeSequence()
            ->push($this->rssPayload([
                ['guid' => 'guid-1', 'url' => 'https://www.nasa.gov/a', 'title' => 'Alpha'],
                ['guid' => 'guid-2', 'url' => 'https://www.nasa.gov/b', 'title' => 'Beta'],
            ]), 200)
            ->push($this->rssPayload([
                ['guid' => 'guid-1', 'url' => 'https://www.nasa.gov/a', 'title' => 'Alpha'],
                ['guid' => 'guid-2', 'url' => 'https://www.nasa.gov/b', 'title' => 'Beta'],
            ]), 200)
            ->push($this->rssPayload([
                ['guid' => 'guid-2', 'url' => 'https://www.nasa.gov/b', 'title' => 'Beta updated'],
            ]), 200);

        $service = app(AstroBotRssService::class);

        $first = $service->sync();
        $this->assertSame(2, $first['added']);
        $this->assertSame(0, $first['updated']);
        $this->assertSame(0, $first['deleted']);
        $this->assertDatabaseCount('rss_items', 2);
        $this->assertDatabaseHas('rss_items', ['status' => RssItem::STATUS_DRAFT]);

        $second = $service->sync();
        $this->assertSame(0, $second['added']);
        $this->assertSame(0, $second['updated']);
        $this->assertSame(0, $second['deleted']);
        $this->assertDatabaseCount('rss_items', 2);

        $third = $service->sync();
        $this->assertSame(0, $third['added']);
        $this->assertSame(1, $third['updated']);
        $this->assertSame(1, $third['deleted']);
        $this->assertDatabaseCount('rss_items', 1);
        $this->assertDatabaseHas('rss_items', ['guid' => 'guid-2', 'title' => 'Beta updated']);
    }

    public function test_sync_handles_http_500_and_invalid_xml_without_crashing_or_mutating_db(): void
    {
        config()->set('astrobot.rss_url', 'https://example.test/rss');
        Log::spy();

        Http::fake(['*' => Http::response('server error', 500)]);
        $first = app(AstroBotRssService::class)->sync();
        $this->assertSame(1, $first['errors']);
        $this->assertDatabaseCount('rss_items', 0);

        Http::fake(['*' => Http::response('<rss><channel><item><title>broken', 200)]);
        $second = app(AstroBotRssService::class)->sync();
        $this->assertSame(1, $second['errors']);
        $this->assertDatabaseCount('rss_items', 0);
        Log::shouldHaveReceived('warning')->atLeast()->once();
    }

    public function test_admin_sync_endpoint_requires_admin_and_auto_publishes_safe_items(): void
    {
        config()->set('astrobot.rss_url', 'https://example.test/rss');
        config()->set('astrobot.auto_publish_enabled', true);
        config()->set('astrobot.domain_whitelist', ['nasa.gov']);
        config()->set('astrobot.risk_keywords', []);

        Http::fake([
            '*' => Http::response($this->rssPayload([
                ['guid' => 'guid-admin-sync', 'url' => 'https://nasa.gov/admin', 'title' => 'Admin sync'],
            ]), 200),
        ]);

        $this->postJson('/api/admin/astrobot/sync')->assertStatus(401);

        $nonAdmin = User::factory()->create(['is_admin' => false, 'is_active' => true]);
        Sanctum::actingAs($nonAdmin);
        $this->postJson('/api/admin/astrobot/sync')->assertStatus(403);

        $admin = User::factory()->create(['is_admin' => true, 'role' => 'admin', 'is_active' => true]);
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/admin/astrobot/sync');
        $response->assertOk();
        $response->assertJsonPath('result.added', 1);
        $response->assertJsonPath('result.published', 1);

        $this->assertDatabaseHas('rss_items', [
            'guid' => 'guid-admin-sync',
            'status' => RssItem::STATUS_PUBLISHED,
        ]);
    }

    public function test_auto_publish_off_sends_items_to_needs_review(): void
    {
        config()->set('astrobot.rss_url', 'https://example.test/rss');
        config()->set('astrobot.auto_publish_enabled', false);

        Http::fake([
            '*' => Http::response($this->rssPayload([
                ['guid' => 'guid-review-1', 'url' => 'https://nasa.gov/review', 'title' => 'Needs review'],
            ]), 200),
        ]);

        $admin = User::factory()->create(['is_admin' => true, 'role' => 'admin', 'is_active' => true]);
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/admin/astrobot/sync');
        $response->assertOk();
        $response->assertJsonPath('result.needs_review', 1);

        $this->assertDatabaseHas('rss_items', [
            'guid' => 'guid-review-1',
            'status' => RssItem::STATUS_NEEDS_REVIEW,
        ]);
    }

    public function test_admin_sync_endpoint_is_rate_limited(): void
    {
        config()->set('astrobot.rss_url', 'https://example.test/rss');

        Http::fake([
            '*' => Http::response($this->rssPayload([
                ['guid' => 'guid-rate-1', 'url' => 'https://nasa.gov/r1', 'title' => 'Rate 1'],
            ]), 200),
        ]);

        $admin = User::factory()->create(['is_admin' => true, 'role' => 'admin', 'is_active' => true]);
        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/astrobot/sync')->assertStatus(200);
        $this->postJson('/api/admin/astrobot/sync')->assertStatus(200);
        $this->postJson('/api/admin/astrobot/sync')->assertStatus(429);
    }

    /**
     * @param array<int,array{guid:string,url:string,title:string}> $rows
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
                htmlspecialchars($row['title'], ENT_QUOTES)
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

