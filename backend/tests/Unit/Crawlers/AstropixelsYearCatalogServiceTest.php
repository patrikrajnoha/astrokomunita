<?php

namespace Tests\Unit\Crawlers;

use App\Services\Crawlers\Astropixels\AstropixelsYearCatalogService;
use App\Support\Http\SslVerificationPolicy;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AstropixelsYearCatalogServiceTest extends TestCase
{
    public function test_it_extracts_available_cet_years_from_catalog_page(): void
    {
        config()->set('events.astropixels.catalog_fetch_during_tests', true);
        config()->set('events.astropixels.catalog_url', 'https://astropixels.test/almanac/almanac.html');
        config()->set('events.astropixels.min_year', 2021);
        config()->set('events.astropixels.max_year', 2100);

        $html = <<<HTML
            <html><body>
              <a href="almanac26/almanac2026cet.html">2026</a>
              <a href="almanac27/almanac2027cet.html">2027</a>
              <a href="almanac31/almanac2031cet.html">2031</a>
              <a href="almanac31/almanac2031gmt.html">2031 GMT</a>
            </body></html>
        HTML;

        Http::fake([
            'https://astropixels.test/*' => Http::response($html, 200),
        ]);

        $service = new AstropixelsYearCatalogService(app(SslVerificationPolicy::class));
        $snapshot = $service->snapshot(forceRefresh: true);

        $this->assertSame('ok', $snapshot['status']);
        $this->assertSame([2026, 2027, 2031], $snapshot['available_years']);
        $this->assertTrue($service->isYearAvailable(2027));
        $this->assertFalse($service->isYearAvailable(2050));
    }
}
