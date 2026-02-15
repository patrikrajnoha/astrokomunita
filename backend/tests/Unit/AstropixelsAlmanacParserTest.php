<?php

namespace Tests\Unit;

use App\Services\Crawlers\Astropixels\AstropixelsAlmanacParser;
use DomainException;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class AstropixelsAlmanacParserTest extends TestCase
{
    public function test_parser_parses_fixture_year_2026(): void
    {
        $html = File::get(base_path('tests/Fixtures/astropixels/almanac2026cet.html'));
        $parser = new AstropixelsAlmanacParser();

        $items = $parser->parse($html, 2026, 'https://astropixels.com/almanac/almanac21/almanac2026cet.html');

        $this->assertNotEmpty($items);
        $this->assertNotEmpty($items[0]->title);
        $this->assertNotEmpty($items[0]->sourceUrl);
        $this->assertSame('UTC', $items[0]->startsAtUtc->timezoneName);
    }

    public function test_parser_parses_fixture_year_2025(): void
    {
        $html = File::get(base_path('tests/Fixtures/astropixels/almanac2025cet.html'));
        $parser = new AstropixelsAlmanacParser();

        $items = $parser->parse($html, 2025, 'https://astropixels.com/almanac/almanac21/almanac2025cet.html');

        $this->assertGreaterThan(0, count($items));
    }

    public function test_parser_throws_domain_exception_when_structure_changes(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('expected <pre> almanac blocks');

        $parser = new AstropixelsAlmanacParser();
        $parser->parse('<html><body><div>No almanac rows</div></body></html>', 2026, 'https://example.test');
    }
}
