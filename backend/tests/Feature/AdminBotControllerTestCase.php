<?php

namespace Tests\Feature;

use App\Enums\BotSourceType;
use App\Models\BotItem;
use App\Models\BotSource;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

abstract class AdminBotControllerTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('moderation.enabled', false);
    }

    protected function actingAsAdmin(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
            'role' => 'admin',
            'is_active' => true,
        ]);

        Sanctum::actingAs($admin);
    }

    protected function createRssSource(string $key): BotSource
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
    protected function createBotItem(BotSource $source, string $stableKey, Carbon $fetchedAt, array $overrides = []): BotItem
    {
        $payload = array_replace([
            'bot_identity' => 'kozmo',
            'source_id' => $source->id,
            'run_id' => null,
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

    protected function singleItemRss(): string
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
