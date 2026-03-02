<?php

namespace Tests\Unit;

use App\Services\Crawlers\Imo\ImoParser;
use DomainException;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ImoParserTest extends TestCase
{
    public function test_parser_parses_fixture_for_requested_year(): void
    {
        $html = File::get(base_path('tests/Fixtures/imo/calendar_sample.html'));
        $parser = new ImoParser();

        $result = $parser->parse($html, 2026, 'https://www.imo.net/resources/calendar/');

        $this->assertCount(2, $result->items);

        $lyrids = collect($result->items)->first(fn ($item) => str_contains($item->title, 'Lyrids'));
        $this->assertNotNull($lyrids);
        $this->assertSame('meteor_shower', $lyrids->eventType);
        $this->assertSame('imo:lyrids-lyr:20260422', $lyrids->externalId);
        $this->assertSame('2026-04-22 20:00:00', $lyrids->startsAtUtc->format('Y-m-d H:i:s'));
        $this->assertSame('peak', $lyrids->timeType);
        $this->assertSame('exact', $lyrids->timePrecision);
        $this->assertSame(18, $lyrids->rawPayload['zhr']);
        $this->assertSame('18:04 +34', $lyrids->rawPayload['radiant']);
        $this->assertSame(49.0, $lyrids->rawPayload['velocity_km_s']);

        $eta = collect($result->items)->first(fn ($item) => str_contains($item->title, 'Eta Aquariids'));
        $this->assertNotNull($eta);
        $this->assertSame('2026-05-06 00:00:00', $eta->startsAtUtc->format('Y-m-d H:i:s'));
        $this->assertSame('peak', $eta->timeType);
        $this->assertSame('unknown', $eta->timePrecision);
        $this->assertFalse($eta->rawPayload['peak_time_known']);
        $this->assertSame(50, $eta->rawPayload['zhr']);
    }

    public function test_parser_logs_diagnostics_and_skips_broken_blocks(): void
    {
        $html = File::get(base_path('tests/Fixtures/imo/calendar_sample.html'));
        $parser = new ImoParser();

        $result = $parser->parse($html, 2026, 'https://www.imo.net/resources/calendar/');

        $this->assertNotEmpty($result->diagnostics);
        $this->assertTrue(
            collect($result->diagnostics)->contains(
                fn (string $line): bool => str_contains($line, 'missing peak block')
            )
        );
    }

    public function test_parser_throws_when_shower_blocks_missing(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('shower blocks not found');

        $parser = new ImoParser();
        $parser->parse('<html><body><div>No shower blocks</div></body></html>', 2026, 'https://www.imo.net/resources/calendar/');
    }
}
