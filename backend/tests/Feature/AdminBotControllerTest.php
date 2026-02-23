<?php

namespace Tests\Feature;

use App\Enums\BotSourceType;
use App\Models\BotItem;
use App\Models\BotRun;
use App\Models\BotSource;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\BotSourceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminBotControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('moderation.enabled', false);
    }

    public function test_non_admin_gets_403_for_all_bot_admin_endpoints(): void
    {
        $source = $this->createRssSource('secure_rss_source');

        $user = User::factory()->create([
            'is_admin' => false,
            'role' => 'user',
            'is_active' => true,
        ]);
        Sanctum::actingAs($user);

        $this->getJson('/api/admin/bots/sources')->assertStatus(403);
        $this->getJson('/api/admin/bots/runs')->assertStatus(403);
        $this->getJson('/api/admin/bots/items?run_id=1')->assertStatus(403);
        $this->postJson('/api/admin/bots/run/' . $source->key)->assertStatus(403);
    }

    public function test_admin_get_sources_returns_seeded_bot_sources(): void
    {
        $this->seed(BotSourceSeeder::class);
        $this->actingAsAdmin();

        $response = $this->getJson('/api/admin/bots/sources');

        $response
            ->assertOk()
            ->assertJsonCount(3, 'data');

        $keys = collect($response->json('data'))->pluck('key')->all();
        $this->assertContains('nasa_rss_breaking', $keys);
        $this->assertContains('nasa_apod_daily', $keys);
        $this->assertContains('wiki_onthisday_astronomy', $keys);
    }

    public function test_admin_post_run_executes_source_and_returns_run_summary_with_stats(): void
    {
        $source = $this->createRssSource('admin_rss_source');
        $this->actingAsAdmin();

        Http::fake([
            $source->url => Http::response($this->singleItemRss(), 200, ['Content-Type' => 'application/rss+xml']),
        ]);

        $response = $this->postJson('/api/admin/bots/run/' . $source->key);

        $response
            ->assertOk()
            ->assertJsonPath('source_key', $source->key)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('stats.fetched_count', 1)
            ->assertJsonPath('stats.published_count', 1);

        $runId = (int) $response->json('run_id');
        $this->assertGreaterThan(0, $runId);
        $this->assertDatabaseHas('bot_runs', [
            'id' => $runId,
            'source_id' => $source->id,
            'status' => 'success',
        ]);
    }

    public function test_admin_post_run_is_throttled_on_second_quick_call(): void
    {
        $source = $this->createRssSource('throttle_rss_source');
        $this->actingAsAdmin();

        Http::fake([
            $source->url => Http::response($this->singleItemRss(), 200, ['Content-Type' => 'application/rss+xml']),
        ]);

        $first = $this->postJson('/api/admin/bots/run/' . $source->key);
        $second = $this->postJson('/api/admin/bots/run/' . $source->key);

        $first->assertOk();
        $second
            ->assertStatus(429)
            ->assertJsonStructure(['message', 'retry_after']);

        $this->assertGreaterThanOrEqual(1, (int) $second->json('retry_after'));
    }

    public function test_admin_get_runs_returns_latest_run_with_pagination_payload(): void
    {
        $source = $this->createRssSource('runs_rss_source');
        $older = BotRun::query()->create([
            'bot_identity' => 'kozmo',
            'source_id' => $source->id,
            'started_at' => now()->subMinutes(10),
            'finished_at' => now()->subMinutes(9),
            'status' => 'failed',
            'stats' => ['failed_count' => 1],
            'error_text' => str_repeat('x', 1300),
        ]);
        $latest = BotRun::query()->create([
            'bot_identity' => 'kozmo',
            'source_id' => $source->id,
            'started_at' => now()->subMinutes(3),
            'finished_at' => now()->subMinutes(2),
            'status' => 'success',
            'stats' => ['published_count' => 1],
            'error_text' => null,
        ]);

        $this->actingAsAdmin();

        $response = $this->getJson('/api/admin/bots/runs?per_page=20');

        $response
            ->assertOk()
            ->assertJsonPath('current_page', 1)
            ->assertJsonPath('per_page', 20)
            ->assertJsonPath('data.0.id', $latest->id)
            ->assertJsonPath('data.0.source_id', $source->id)
            ->assertJsonPath('data.0.source_key', $source->key)
            ->assertJsonPath('data.0.status', 'success')
            ->assertJsonPath('data.1.id', $older->id);

        $truncatedError = (string) data_get($response->json(), 'data.1.error_text', '');
        $length = function_exists('mb_strlen') ? mb_strlen($truncatedError) : strlen($truncatedError);
        $this->assertLessThanOrEqual(1000, $length);
    }

    public function test_admin_get_items_by_run_id_returns_items_in_run_window_with_buffer(): void
    {
        $source = $this->createRssSource('items_run_source');
        $otherSource = $this->createRssSource('items_other_source');

        $runStart = Carbon::parse('2026-02-22 10:00:00');
        $runFinish = Carbon::parse('2026-02-22 10:10:00');

        $run = BotRun::query()->create([
            'bot_identity' => 'kozmo',
            'source_id' => $source->id,
            'started_at' => $runStart,
            'finished_at' => $runFinish,
            'status' => 'success',
            'stats' => ['published_count' => 1],
            'error_text' => null,
        ]);

        $insideOne = $this->createBotItem($source, 'inside-one', $runStart->copy()->addMinute(), [
            'title' => 'Inside One',
            'publish_status' => 'published',
            'translation_status' => 'done',
            'meta' => [
                'skip_reason' => null,
                'used_translation' => true,
            ],
        ]);
        $insideTwo = $this->createBotItem($source, 'inside-two', $runFinish->copy()->addMinute(), [
            'title' => 'Inside Two',
            'publish_status' => 'skipped',
            'translation_status' => 'skipped',
            'meta' => [
                'skip_reason' => 'no_relevant_events',
                'used_translation' => false,
            ],
        ]);

        $this->createBotItem($source, 'outside-before', $runStart->copy()->subMinutes(3));
        $this->createBotItem($source, 'outside-after', $runFinish->copy()->addMinutes(3));
        $this->createBotItem($otherSource, 'other-source', $runStart->copy()->addMinute());

        $this->actingAsAdmin();

        $response = $this->getJson('/api/admin/bots/items?run_id=' . $run->id . '&per_page=50');

        $response
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonCount(2, 'data');

        $keys = collect($response->json('data'))->pluck('stable_key')->all();
        $this->assertContains($insideOne->stable_key, $keys);
        $this->assertContains($insideTwo->stable_key, $keys);
        $this->assertNotContains('outside-before', $keys);
        $this->assertNotContains('outside-after', $keys);
        $this->assertNotContains('other-source', $keys);

        $insideTwoPayload = collect($response->json('data'))
            ->first(fn (array $row): bool => (string) ($row['stable_key'] ?? '') === 'inside-two');
        $this->assertSame('skipped', (string) ($insideTwoPayload['publish_status'] ?? ''));
        $this->assertSame('no_relevant_events', (string) ($insideTwoPayload['skip_reason'] ?? ''));
        $this->assertFalse((bool) ($insideTwoPayload['used_translation'] ?? true));
    }

    public function test_admin_get_items_pagination_works_for_run_id_filter(): void
    {
        $source = $this->createRssSource('items_pagination_source');

        $runStart = Carbon::parse('2026-02-22 12:00:00');
        $runFinish = Carbon::parse('2026-02-22 12:20:00');

        $run = BotRun::query()->create([
            'bot_identity' => 'kozmo',
            'source_id' => $source->id,
            'started_at' => $runStart,
            'finished_at' => $runFinish,
            'status' => 'success',
            'stats' => ['fetched_count' => 25],
            'error_text' => null,
        ]);

        for ($i = 1; $i <= 25; $i++) {
            $this->createBotItem(
                $source,
                sprintf('item-%02d', $i),
                $runStart->copy()->addSeconds($i),
                ['title' => sprintf('Item %02d', $i)]
            );
        }

        $this->actingAsAdmin();

        $response = $this->getJson('/api/admin/bots/items?run_id=' . $run->id . '&per_page=10&page=2');

        $response
            ->assertOk()
            ->assertJsonPath('current_page', 2)
            ->assertJsonPath('per_page', 10)
            ->assertJsonPath('total', 25)
            ->assertJsonPath('last_page', 3)
            ->assertJsonCount(10, 'data');
    }

    private function actingAsAdmin(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
            'role' => 'admin',
            'is_active' => true,
        ]);

        Sanctum::actingAs($admin);
    }

    private function createRssSource(string $key): BotSource
    {
        return BotSource::query()->create([
            'key' => strtolower(trim($key)),
            'bot_identity' => 'kozmo',
            'source_type' => BotSourceType::RSS->value,
            'url' => 'https://example.test/bot-rss.xml',
            'is_enabled' => true,
            'schedule' => null,
        ]);
    }

    /**
     * @param array<string,mixed> $overrides
     */
    private function createBotItem(BotSource $source, string $stableKey, Carbon $fetchedAt, array $overrides = []): BotItem
    {
        $payload = array_replace([
            'bot_identity' => 'kozmo',
            'source_id' => $source->id,
            'post_id' => null,
            'stable_key' => $stableKey,
            'title' => 'Test item title',
            'summary' => 'Summary text long enough for content checks.',
            'content' => 'Summary text long enough for content checks.',
            'url' => 'https://example.test/item/' . rawurlencode($stableKey),
            'published_at' => null,
            'fetched_at' => $fetchedAt,
            'lang_original' => 'en',
            'lang_detected' => null,
            'title_translated' => null,
            'content_translated' => null,
            'translation_status' => 'pending',
            'publish_status' => 'pending',
            'meta' => null,
        ], $overrides);

        return BotItem::query()->create($payload);
    }

    private function singleItemRss(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <title>NASA News Releases</title>
    <item>
      <title>Admin RSS Item</title>
      <link>https://www.nasa.gov/news-release/admin-rss-item/</link>
      <guid isPermaLink="false">admin-rss-item-guid</guid>
      <pubDate>Thu, 19 Feb 2026 08:00:00 GMT</pubDate>
      <description><![CDATA[<p>Body text with enough content length to pass publish validation checks in bots pipeline.</p>]]></description>
    </item>
  </channel>
</rss>
XML;
    }
}
