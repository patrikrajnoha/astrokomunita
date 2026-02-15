<?php

namespace App\Services\Crawlers;

use App\Enums\EventSource;
use App\Services\Crawlers\Astropixels\AstropixelsAlmanacParser;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\Http;
use Throwable;

class AstropixelsCrawlerService implements CrawlerInterface
{
    public function __construct(
        private readonly AstropixelsAlmanacParser $parser,
    ) {
    }

    public function fetchCandidates(CrawlContext $context): CandidateBatch
    {
        $url = $this->buildUrlForYear($context->year);
        $verifyOption = $this->resolveSslVerifyOption();

        $response = Http::timeout(20)
            ->retry(2, 500)
            ->withOptions(['verify' => $verifyOption])
            ->withHeaders([
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'sk-SK,sk;q=0.9,en;q=0.8',
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36',
            ])
            ->get($url);

        $response->throw();
        $html = (string) $response->body();

        try {
            $items = $this->parser->parse($html, $context->year, $url, $context->timezone);
        } catch (Throwable $e) {
            $snippet = substr(trim(strip_tags($html)), 0, 1200);
            throw new DomainException(
                'Astropixels parser failed: '
                . $e->getMessage()
                . ' | html_snippet='
                . $snippet
            );
        }

        if ($context->from || $context->to) {
            $items = array_values(array_filter($items, function (CandidateItem $item) use ($context) {
                if ($context->from && $item->startsAtUtc->lt($context->from)) {
                    return false;
                }
                if ($context->to && $item->startsAtUtc->gt($context->to)) {
                    return false;
                }
                return true;
            }));
        }

        return new CandidateBatch(
            source: EventSource::ASTROPIXELS,
            year: $context->year,
            fetchedAt: CarbonImmutable::now('UTC'),
            items: $items,
            fetchedBytes: strlen($html),
        );
    }

    private function buildUrlForYear(int $year): string
    {
        $pattern = (string) config('events.astropixels.base_url_pattern', 'https://astropixels.com/almanac/almanac21/almanac%dcet.html');
        return sprintf($pattern, $year);
    }

    private function resolveSslVerifyOption(): bool|string
    {
        $caBundle = (string) config('events.crawler_ssl_ca_bundle', '');
        if ($caBundle !== '') {
            if (!is_file($caBundle)) {
                throw new DomainException("Astropixels crawler SSL: CA bundle file not found at '{$caBundle}'.");
            }

            return $caBundle;
        }

        return (bool) config('events.crawler_ssl_verify', true);
    }
}
