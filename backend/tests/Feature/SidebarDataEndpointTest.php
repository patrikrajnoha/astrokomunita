<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use App\Models\Event;
use App\Models\User;
use App\Services\TranslationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SidebarDataEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Cache::flush();
        parent::tearDown();
    }

    public function test_it_returns_requested_sidebar_widget_payloads_in_one_response(): void
    {
        Cache::flush();
        $author = User::factory()->create();

        $event = Event::query()->create([
            'title' => 'Perzeidy',
            'type' => 'meteor_shower',
            'start_at' => now()->addDay(),
            'max_at' => now()->addDay(),
            'visibility' => 1,
            'source_name' => 'manual',
            'source_uid' => 'sidebar-bundle-event',
            'source_hash' => 'sidebar-bundle-event',
        ]);

        BlogPost::query()->create([
            'user_id' => $author->id,
            'title' => 'Sidebar article',
            'slug' => 'sidebar-article',
            'content' => 'x',
            'views' => 12,
            'published_at' => now()->subMinute(),
            'created_at' => now()->subMinute(),
            'updated_at' => now()->subMinute(),
        ]);

        $response = $this->getJson('/api/sidebar-data?sections[]=next_event&sections[]=upcoming_events&sections[]=latest_articles&sections[]=unknown')
            ->assertOk()
            ->assertJsonPath('requested_sections.0', 'next_event')
            ->assertJsonPath('requested_sections.1', 'upcoming_events')
            ->assertJsonPath('requested_sections.2', 'latest_articles')
            ->assertJsonMissingPath('data.unknown');

        $this->assertSame($event->id, $response->json('data.next_event.data.id'));
        $this->assertSame($event->id, $response->json('data.upcoming_events.items.0.id'));
        $this->assertSame('sidebar-article', $response->json('data.latest_articles.latest.0.slug'));
    }

    public function test_it_can_bundle_nasa_payload(): void
    {
        Cache::flush();
        Http::preventStrayRequests();
        $this->mock(TranslationService::class, function ($mock): void {
            $mock->shouldReceive('translateEnToSk')
                ->twice()
                ->andReturnUsing(static fn (string $text) => 'SK ' . $text);
        });

        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8" ?>
<rss version="2.0">
  <channel>
    <item>
      <title>Bundled NASA</title>
      <link>https://www.nasa.gov/image-detail/bundled/</link>
      <description>Bundled description</description>
      <enclosure url="https://www.nasa.gov/wp-content/uploads/bundled.jpg" length="1234" type="image/jpeg" />
    </item>
  </channel>
</rss>
XML;

        Http::fake([
            'https://www.nasa.gov/feeds/iotd-feed/*' => Http::response($xml, 200, ['Content-Type' => 'application/rss+xml']),
        ]);

        $this->getJson('/api/sidebar-data?sections=nasa_apod')
            ->assertOk()
            ->assertJsonPath('requested_sections.0', 'nasa_apod')
            ->assertJsonPath('data.nasa_apod.available', true)
            ->assertJsonPath('data.nasa_apod.title', 'SK Bundled NASA')
            ->assertJsonPath('data.nasa_apod.source.label', 'NASA IOTD RSS');
    }
}
