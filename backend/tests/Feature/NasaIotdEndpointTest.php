<?php

namespace Tests\Feature;

use App\Services\TranslationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NasaIotdEndpointTest extends TestCase
{
    protected function tearDown(): void
    {
        Cache::flush();
        parent::tearDown();
    }

    public function test_it_returns_payload_from_rss_feed_when_available(): void
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
      <title>Test RSS APOD</title>
      <link>https://www.nasa.gov/image-detail/test-rss/</link>
      <description>RSS description</description>
      <enclosure url="https://www.nasa.gov/wp-content/uploads/test.jpg" length="1234" type="image/jpeg" />
    </item>
  </channel>
</rss>
XML;

        Http::fake([
            'https://www.nasa.gov/feeds/iotd-feed/*' => Http::response($xml, 200, ['Content-Type' => 'application/rss+xml']),
        ]);

        $response = $this->getJson('/api/nasa/iotd')
            ->assertOk()
            ->assertJsonPath('available', true)
            ->assertJsonPath('title', 'SK Test RSS APOD')
            ->assertJsonPath('image_url', 'https://www.nasa.gov/wp-content/uploads/test.jpg')
            ->assertJsonPath('link', 'https://www.nasa.gov/image-detail/test-rss/');

        $this->assertSame('SK RSS description', (string) $response->json('excerpt'));
    }

    public function test_it_falls_back_to_apod_api_when_rss_feed_fails(): void
    {
        Cache::flush();
        Http::preventStrayRequests();
        $this->mock(TranslationService::class, function ($mock): void {
            $mock->shouldReceive('translateEnToSk')
                ->twice()
                ->andReturnUsing(static fn (string $text) => 'SK ' . $text);
        });

        Http::fake([
            'https://www.nasa.gov/feeds/iotd-feed/*' => Http::response('feed unavailable', 500),
            'https://api.nasa.gov/planetary/apod*' => Http::response([
                'title' => 'APOD fallback title',
                'explanation' => 'Fallback explanation',
                'url' => 'https://apod.nasa.gov/apod/image/test.jpg',
                'hdurl' => 'https://apod.nasa.gov/apod/image/test-hd.jpg',
                'media_type' => 'image',
            ], 200),
        ]);

        $this->getJson('/api/nasa/iotd')
            ->assertOk()
            ->assertJsonPath('available', true)
            ->assertJsonPath('title', 'SK APOD fallback title')
            ->assertJsonPath('excerpt', 'SK Fallback explanation')
            ->assertJsonPath('image_url', 'https://apod.nasa.gov/apod/image/test.jpg')
            ->assertJsonPath('link', 'https://apod.nasa.gov/apod/image/test-hd.jpg');
    }

    public function test_it_falls_back_to_original_text_when_translation_service_fails(): void
    {
        Cache::flush();
        Http::preventStrayRequests();
        $this->mock(TranslationService::class, function ($mock): void {
            $mock->shouldReceive('translateEnToSk')
                ->twice()
                ->andThrow(new \RuntimeException('translation unavailable'));
        });

        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8" ?>
<rss version="2.0">
  <channel>
    <item>
      <title>Original RSS Title</title>
      <link>https://www.nasa.gov/image-detail/test-rss/</link>
      <description>Original RSS description</description>
      <enclosure url="https://www.nasa.gov/wp-content/uploads/test.jpg" length="1234" type="image/jpeg" />
    </item>
  </channel>
</rss>
XML;

        Http::fake([
            'https://www.nasa.gov/feeds/iotd-feed/*' => Http::response($xml, 200, ['Content-Type' => 'application/rss+xml']),
        ]);

        $this->getJson('/api/nasa/iotd')
            ->assertOk()
            ->assertJsonPath('available', true)
            ->assertJsonPath('title', 'Original RSS Title')
            ->assertJsonPath('excerpt', 'Original RSS description');
    }
}
