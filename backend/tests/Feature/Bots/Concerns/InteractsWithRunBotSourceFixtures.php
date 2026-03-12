<?php

namespace Tests\Feature\Bots\Concerns;

use App\Enums\BotSourceType;
use App\Models\BotSource;
use App\Models\Post;
use App\Models\User;
use Carbon\Carbon;

trait InteractsWithRunBotSourceFixtures
{
    protected function configureRunBotSourceDefaults(): void
    {
        config()->set('moderation.enabled', false);
        config()->set('services.nasa.key', 'test-nasa-key');
        config()->set('bots.sources.nasa_apod_daily.requires_api_key', true);
        config()->set('bots.sources.nasa_apod_daily.rate_limit_backoff_minutes', 360);
        config()->set('bots.sources.nasa_apod_daily.enable_rss_fallback', false);
        config()->set('bots.sources.nasa_apod_daily.rss_fallback_url', 'https://apod.nasa.gov/apod.rss');
    }

    protected function createSource(): BotSource
    {
        return BotSource::query()->create([
            'key' => 'nasa_rss_breaking',
            'bot_identity' => 'kozmo',
            'source_type' => BotSourceType::RSS->value,
            'url' => 'https://example.test/nasa.xml',
            'is_enabled' => true,
            'schedule' => null,
        ]);
    }

    protected function createApodSource(): BotSource
    {
        return BotSource::query()->create([
            'key' => 'nasa_apod_daily',
            'bot_identity' => 'stela',
            'source_type' => BotSourceType::API->value,
            'url' => 'https://api.nasa.gov/planetary/apod',
            'is_enabled' => true,
            'schedule' => null,
        ]);
    }

    protected function createWikipediaSource(): BotSource
    {
        return BotSource::query()->create([
            'key' => 'wiki_onthisday_astronomy',
            'bot_identity' => 'kozmo',
            'source_type' => BotSourceType::WIKIPEDIA->value,
            'url' => 'https://example.test/wiki/onthisday/all',
            'is_enabled' => true,
            'schedule' => null,
        ]);
    }

    protected function fixtureRss(): string
    {
        return (string) file_get_contents(base_path('tests/Fixtures/nasa_rss.xml'));
    }

    /**
     * @return array<string,mixed>
     */
    protected function wikiFixturePayload(): array
    {
        $decoded = json_decode(
            (string) file_get_contents(base_path('tests/Fixtures/wiki_onthisday.json')),
            true
        );

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array<string,mixed>
     */
    protected function wikiNoRelevantPayload(): array
    {
        return [
            'events' => [
                [
                    'year' => 1400,
                    'text' => 'A local market opened in a medieval city center.',
                    'pages' => [[
                        'title' => 'Marketplace',
                        'content_urls' => [
                            'desktop' => [
                                'page' => 'https://en.wikipedia.org/wiki/Market_square',
                            ],
                        ],
                    ]],
                ],
                [
                    'year' => 1720,
                    'text' => 'A bridge construction project was completed near a river.',
                    'pages' => [[
                        'title' => 'Bridge',
                        'content_urls' => [
                            'desktop' => [
                                'page' => 'https://en.wikipedia.org/wiki/Bridge',
                            ],
                        ],
                    ]],
                ],
            ],
            'births' => [],
            'deaths' => [],
            'holidays' => [],
        ];
    }

    protected function wikiEndpointForDate(string $baseUrl, Carbon $date): string
    {
        return sprintf('%s/%02d/%02d', rtrim($baseUrl, '/'), $date->month, $date->day);
    }

    /**
     * @param array<string,mixed> $overrides
     * @return array<string,mixed>
     */
    protected function apodPayload(array $overrides = []): array
    {
        return array_replace([
            'date' => '2026-02-20',
            'title' => 'APOD Test Title',
            'explanation' => 'APOD explanation text long enough to pass content checks for publishing.',
            'url' => 'https://apod.nasa.gov/apod/image/test.jpg',
            'hdurl' => 'https://apod.nasa.gov/apod/image/test-hd.jpg',
            'media_type' => 'image',
            'copyright' => 'NASA/ESA',
        ], $overrides);
    }

    protected function apodRssPayload(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0" xmlns:media="http://search.yahoo.com/mrss/">
  <channel>
    <title>APOD</title>
    <item>
      <title>APOD: 2026-02-20 Test fallback title</title>
      <link>https://apod.nasa.gov/apod/ap260220.html</link>
      <pubDate>Fri, 20 Feb 2026 12:00:00 GMT</pubDate>
      <description><![CDATA[
        <p>Fallback explanation text long enough for publishing.</p>
        <img src="https://apod.nasa.gov/apod/calendar/S_260220.jpg" />
      ]]></description>
      <enclosure url="https://apod.nasa.gov/apod/calendar/S_260220.jpg" length="12345" type="image/jpeg" />
      <media:content url="https://apod.nasa.gov/apod/calendar/S_260220.jpg" medium="image" />
    </item>
  </channel>
</rss>
XML;
    }

    protected function apodRssArticleHtml(): string
    {
        return <<<'HTML'
<!doctype html>
<html>
  <body>
    <a href="image/test-full.jpg">
      <img src="calendar/S_260220.jpg" />
    </a>
    <p>Fallback APOD article page.</p>
  </body>
</html>
HTML;
    }

    protected function imageFixtureBinary(): string
    {
        return (string) file_get_contents(base_path('tests/Fixtures/images/large-sample.jpg'));
    }

    protected function rssWithMissingTitleAndLink(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <title>NASA News Releases</title>
    <item>
      <guid isPermaLink="false">guid-missing-title-and-link</guid>
      <pubDate>Wed, 18 Feb 2026 11:00:00 GMT</pubDate>
      <description><![CDATA[<p>Body exists but title and link are missing.</p>]]></description>
    </item>
  </channel>
</rss>
XML;
    }

    protected function rssForGuid(string $guid): string
    {
        return sprintf(
            <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <title>NASA News Releases</title>
    <item>
      <title>Existing linked item</title>
      <link>https://www.nasa.gov/news-release/prelinked/</link>
      <guid isPermaLink="false">%s</guid>
      <pubDate>Thu, 19 Feb 2026 08:00:00 GMT</pubDate>
      <description><![CDATA[<p>Body text with enough length to pass content checks.</p>]]></description>
    </item>
  </channel>
</rss>
XML,
            $guid
        );
    }

    protected function rssSingleItem(string $guid, string $title, string $link, string $description): string
    {
        return sprintf(
            <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <title>NASA News Releases</title>
    <item>
      <title>%s</title>
      <link>%s</link>
      <guid isPermaLink="false">%s</guid>
      <pubDate>Thu, 19 Feb 2026 08:00:00 GMT</pubDate>
      <description><![CDATA[<p>%s</p>]]></description>
    </item>
  </channel>
</rss>
XML,
            htmlspecialchars($title, ENT_QUOTES | ENT_XML1),
            htmlspecialchars($link, ENT_QUOTES | ENT_XML1),
            htmlspecialchars($guid, ENT_QUOTES | ENT_XML1),
            htmlspecialchars($description, ENT_QUOTES | ENT_XML1)
        );
    }

    protected function createExistingBotPost(): Post
    {
        $user = User::factory()->create([
            'is_bot' => true,
            'username' => 'kozmo',
            'email' => 'kozmo-existing@astrokomunita.local',
        ]);

        return Post::query()->create([
            'user_id' => $user->id,
            'feed_key' => 'astro',
            'author_kind' => 'bot',
            'bot_identity' => 'kozmo',
            'content' => 'Already published bot post',
            'source_name' => 'bot_nasa_rss_breaking',
            'source_uid' => sha1('nasa_rss_breaking|nasa-guid-existing'),
            'source_url' => 'https://www.nasa.gov/news-release/prelinked/',
            'source_published_at' => now(),
            'is_hidden' => false,
            'moderation_status' => 'ok',
        ]);
    }
}
