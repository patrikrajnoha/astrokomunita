<?php

namespace Tests\Feature;

use App\Models\AstroBotRun;
use App\Models\Post;
use App\Models\RssItem;
use App\Models\User;
use App\Services\AstroBotNasaService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AstroBotRssServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_creates_published_post_from_rss(): void
    {
        config()->set('astrobot.nasa_rss_url', 'https://example.test/nasa-rss');
        config()->set('astrobot.keep_max_items', 30);
        config()->set('astrobot.keep_max_days', 14);

        Http::fake([
            '*' => Http::response($this->rssPayload([
                ['guid' => 'nasa-guid-1', 'url' => 'https://www.nasa.gov/news/a', 'title' => 'NASA Alpha'],
            ]), 200),
        ]);

        $result = app(AstroBotNasaService::class)->runSync('test');

        $this->assertSame(1, $result['new']);
        $this->assertSame(1, $result['published']);
        $this->assertDatabaseHas('rss_items', [
            'source' => AstroBotNasaService::SOURCE,
            'guid' => 'nasa-guid-1',
            'status' => RssItem::STATUS_PUBLISHED,
        ]);
        $this->assertDatabaseHas('posts', [
            'source_name' => 'astrobot',
            'source_url' => 'https://www.nasa.gov/news/a',
        ]);
    }

    public function test_repeated_sync_is_idempotent_and_does_not_create_duplicates(): void
    {
        config()->set('astrobot.nasa_rss_url', 'https://example.test/nasa-rss');

        Http::fakeSequence()
            ->push($this->rssPayload([
                ['guid' => 'idempotent-guid', 'url' => 'https://www.nasa.gov/news/idempotent', 'title' => 'NASA Idempotent'],
            ]), 200)
            ->push($this->rssPayload([
                ['guid' => 'idempotent-guid', 'url' => 'https://www.nasa.gov/news/idempotent', 'title' => 'NASA Idempotent'],
            ]), 200);

        $service = app(AstroBotNasaService::class);
        $service->runSync('test');
        $service->runSync('test');

        $this->assertSame(1, RssItem::query()->where('source', AstroBotNasaService::SOURCE)->count());
        $this->assertSame(1, Post::query()->where('source_url', 'https://www.nasa.gov/news/idempotent')->count());
    }

    public function test_prune_removes_old_nasa_posts(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-12 12:00:00'));
        config()->set('astrobot.keep_max_days', 14);
        config()->set('astrobot.keep_max_items', 30);

        $bot = User::factory()->create([
            'email' => 'astrobot@astrokomunita.local',
            'username' => 'astrobot',
            'is_bot' => true,
        ]);
        $human = User::factory()->create();

        $oldNasaPost = Post::factory()->create([
            'user_id' => $bot->id,
            'source_name' => 'astrobot',
            'created_at' => now()->subDays(20),
            'updated_at' => now()->subDays(20),
            'source_published_at' => now()->subDays(20),
        ]);
        $freshNasaPost = Post::factory()->create([
            'user_id' => $bot->id,
            'source_name' => 'astrobot',
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
            'source_published_at' => now()->subDays(2),
        ]);
        $oldHumanPost = Post::factory()->create([
            'user_id' => $human->id,
            'source_name' => null,
            'created_at' => now()->subDays(25),
            'updated_at' => now()->subDays(25),
        ]);

        RssItem::query()->create([
            'source' => AstroBotNasaService::SOURCE,
            'guid' => 'old-guid',
            'url' => 'https://www.nasa.gov/old',
            'dedupe_hash' => sha1('old-guid'),
            'stable_key' => sha1('old-guid'),
            'title' => 'Old NASA',
            'summary' => 'Old',
            'status' => RssItem::STATUS_PUBLISHED,
            'fetched_at' => now(),
            'published_at' => now()->subDays(20),
            'post_id' => $oldNasaPost->id,
        ]);
        RssItem::query()->create([
            'source' => AstroBotNasaService::SOURCE,
            'guid' => 'fresh-guid',
            'url' => 'https://www.nasa.gov/fresh',
            'dedupe_hash' => sha1('fresh-guid'),
            'stable_key' => sha1('fresh-guid'),
            'title' => 'Fresh NASA',
            'summary' => 'Fresh',
            'status' => RssItem::STATUS_PUBLISHED,
            'fetched_at' => now(),
            'published_at' => now()->subDays(2),
            'post_id' => $freshNasaPost->id,
        ]);

        $deleted = app(AstroBotNasaService::class)->pruneOld();

        $this->assertSame(1, $deleted);
        $this->assertDatabaseMissing('posts', ['id' => $oldNasaPost->id]);
        $this->assertDatabaseHas('posts', ['id' => $freshNasaPost->id]);
        $this->assertDatabaseHas('posts', ['id' => $oldHumanPost->id]);
    }

    public function test_emergency_sync_endpoint_works_only_for_admin(): void
    {
        config()->set('astrobot.nasa_rss_url', 'https://example.test/nasa-rss');
        Http::fake(['*' => Http::response($this->rssPayload([]), 200)]);

        $this->postJson('/api/admin/astrobot/nasa/sync-now')->assertStatus(401);

        $nonAdmin = User::factory()->create(['is_admin' => false, 'is_active' => true]);
        Sanctum::actingAs($nonAdmin);
        $this->postJson('/api/admin/astrobot/nasa/sync-now')->assertStatus(403);

        $admin = User::factory()->create(['is_admin' => true, 'role' => 'admin', 'is_active' => true]);
        Sanctum::actingAs($admin);
        $this->postJson('/api/admin/astrobot/nasa/sync-now')->assertOk();
    }

    public function test_emergency_sync_returns_409_when_lock_is_active(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'role' => 'admin', 'is_active' => true]);
        Sanctum::actingAs($admin);

        $lock = Cache::lock(AstroBotNasaService::LOCK_KEY, 60);
        $this->assertTrue($lock->get());

        try {
            $this->postJson('/api/admin/astrobot/nasa/sync-now')
                ->assertStatus(409);
        } finally {
            $lock->release();
        }
    }

    public function test_fetch_failure_is_audited_and_status_endpoint_returns_error(): void
    {
        config()->set('astrobot.nasa_rss_url', 'https://www.nasa.gov/news-release/feed/');
        config()->set('astrobot.ssl_verify', true);
        config()->set('astrobot.ssl_ca_bundle', null);

        Http::fake(function () {
            throw new ConnectionException('cURL error 60: SSL certificate problem: unable to get local issuer certificate');
        });

        $result = app(AstroBotNasaService::class)->runSync('test');

        $this->assertSame(1, $result['errors']);
        $this->assertStringContainsString('SSL verify failed', (string) $result['error']);

        $run = AstroBotRun::query()->latest('id')->first();
        $this->assertNotNull($run);
        $this->assertSame('error', $run->status);
        $this->assertStringContainsString('SSL verify failed', (string) $run->error_message);

        $admin = User::factory()->create(['is_admin' => true, 'role' => 'admin', 'is_active' => true]);
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/astrobot/nasa/status');
        $response->assertOk();
        $response->assertJsonPath('last_run.status', 'error');
        $response->assertJsonPath('last_run.errors', 1);
        $this->assertStringContainsString('SSL verify failed', (string) data_get($response->json(), 'last_run.error_message'));
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
