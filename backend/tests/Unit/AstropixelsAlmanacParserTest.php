<?php

namespace Tests\Unit;

use App\Services\Crawlers\Astropixels\AstropixelsAlmanacParser;
use DomainException;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class AstropixelsAlmanacParserTest extends TestCase
{
    public function test_parser_parses_fixture_year_2026_and_returns_valid_items(): void
    {
        $html = File::get(base_path('tests/Fixtures/astropixels/almanac2026cet.html'));
        $parser = new AstropixelsAlmanacParser();

        $result = $parser->parse($html, 2026, 'https://astropixels.com/almanac/almanac21/almanac2026cet.html');
        $items = $result->items;

        $this->assertGreaterThan(0, count($items));

        $first = $items[0];
        $last = $items[array_key_last($items)];

        $this->assertNotEmpty($first->title);
        $this->assertNotEmpty($first->sourceUrl);
        $this->assertNotNull($first->externalId);
        $this->assertSame('UTC', $first->startsAtUtc->timezoneName);

        $this->assertNotEmpty($last->title);
        $this->assertNotEmpty($last->sourceUrl);
        $this->assertNotNull($last->externalId);
    }

    public function test_parser_parses_fixture_year_2021(): void
    {
        $html = File::get(base_path('tests/Fixtures/astropixels/almanac2021cet.html'));
        $parser = new AstropixelsAlmanacParser();

        $result = $parser->parse($html, 2021, 'https://astropixels.com/almanac/almanac21/almanac2021cet.html');

        $this->assertGreaterThan(0, count($result->items));
    }

    public function test_parser_converts_bratislava_timezone_to_utc_for_winter_and_summer_rows(): void
    {
        $html = File::get(base_path('tests/Fixtures/astropixels/almanac2026cet.html'));
        $parser = new AstropixelsAlmanacParser();
        $result = $parser->parse($html, 2026, 'https://astropixels.com/almanac/almanac21/almanac2026cet.html');

        $winter = collect($result->items)->first(static fn ($item) => $item->title === 'Moon at Perigee: 360348 km');
        $summer = collect($result->items)->first(static fn ($item) => $item->title === 'Earth at Aphelion: 1.01664 AU');

        $this->assertNotNull($winter);
        $this->assertNotNull($summer);

        // Jan (CET, UTC+1)
        $this->assertSame('2026-01-01 21:43:00', $winter->startsAtUtc->format('Y-m-d H:i:s'));
        // Jul (CEST, UTC+2)
        $this->assertSame('2026-07-06 17:00:00', $summer->startsAtUtc->format('Y-m-d H:i:s'));
    }

    public function test_parser_throws_domain_exception_when_almanac_table_is_missing(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('table not found');

        $parser = new AstropixelsAlmanacParser();
        $parser->parse('<html><body><div>No almanac rows</div></body></html>', 2026, 'https://example.test');
    }
}
