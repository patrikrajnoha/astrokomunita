<?php

namespace Tests\Unit\EventImport;

use App\Services\EventImport\HtmlSourceFetcher;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HtmlSourceFetcherTest extends TestCase
{
    public function test_fetch_retries_with_humans_cookie_on_http_409_challenge(): void
    {
        Http::fake([
            'https://astropixels.com/*' => Http::sequence()
                ->push('<script>document.cookie = "humans_21909=1"; document.location.reload(true)</script>', 409)
                ->push('<html><body>ok</body></html>', 200),
        ]);

        $fetcher = new HtmlSourceFetcher();
        $html = $fetcher->fetch('https://astropixels.com/almanac/almanac21/almanac2026cet.html');

        $this->assertSame('<html><body>ok</body></html>', $html);
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => $request->hasHeader('Cookie', 'humans_21909=1'));
    }
}
