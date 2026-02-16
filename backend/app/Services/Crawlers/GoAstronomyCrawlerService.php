<?php

namespace App\Services\Crawlers;

use App\Enums\EventSource;
use App\Support\Http\SslVerificationPolicy;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class GoAstronomyCrawlerService implements CrawlerInterface
{
    private const REQUEST_TIMEOUT_SECONDS = 20;
    private const REQUEST_CONNECT_TIMEOUT_SECONDS = 10;
    private const REQUEST_RETRY_TIMES = 2;
    private const REQUEST_RETRY_SLEEP_MS = 500;
    private const REQUEST_HEADERS = [
        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'Accept-Language' => 'sk-SK,sk;q=0.9,en;q=0.8',
        'User-Agent' => 'AstrokomunitaCrawler/1.0 (+https://www.go-astronomy.com; research-use)',
    ];

    public function source(): EventSource
    {
        return EventSource::GO_ASTRONOMY;
    }

    public function fetchCandidates(CrawlContext $context): CandidateBatch
    {
        $url = (string) config('events.go_astronomy.calendar_url', 'https://www.go-astronomy.com/astronomy-calendar.php');
        $verifyOption = $this->resolveSslVerifyOption();

        $response = Http::connectTimeout(self::REQUEST_CONNECT_TIMEOUT_SECONDS)
            ->timeout(self::REQUEST_TIMEOUT_SECONDS)
            ->retry(self::REQUEST_RETRY_TIMES, self::REQUEST_RETRY_SLEEP_MS)
            ->withOptions(['verify' => $verifyOption])
            ->withAttributes(['ssl_verify' => $verifyOption])
            ->withHeaders(self::REQUEST_HEADERS)
            ->get($url);

        try {
            $response->throw();
        } catch (RequestException $e) {
            throw new DomainException(
                sprintf(
                    'GO_ASTRONOMY_HTTP_ERROR: GET %s failed with status %s.',
                    $url,
                    $e->response?->status() ?? 'n/a'
                ),
                previous: $e
            );
        }

        $html = (string) $response->body();

        try {
            $items = $this->parseCandidates($html, $context, $url);
        } catch (Throwable $e) {
            throw new DomainException(
                'GO_ASTRONOMY_PARSE_ERROR: ' . $e->getMessage(),
                previous: $e
            );
        }

        return new CandidateBatch(
            source: EventSource::GO_ASTRONOMY,
            year: $context->year,
            fetchedAt: CarbonImmutable::now('UTC'),
            items: $items,
            fetchedBytes: strlen($html),
            sourceUrl: $url,
            headersUsed: self::REQUEST_HEADERS !== [],
            diagnostics: [],
        );
    }

    /**
     * @return array<int, CandidateItem>
     */
    private function parseCandidates(string $html, CrawlContext $context, string $url): array
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);

        /** @var \DOMNodeList<\DOMElement> $rows */
        $rows = $xpath->query('//tr');
        if (! $rows instanceof \DOMNodeList) {
            return [];
        }

        $items = [];

        foreach ($rows as $row) {
            $text = trim(preg_replace('/\s+/u', ' ', (string) $row->textContent) ?? '');
            if ($text === '') {
                continue;
            }

            [$startUtc, $endUtc] = $this->extractDateRangeUtc($text, $context->timezone);
            if (! $startUtc) {
                continue;
            }

            $sourceYear = $this->extractPrimaryYear($text);
            if ($sourceYear !== null && $sourceYear !== $context->year) {
                continue;
            }

            if ($context->from && $startUtc->lt($context->from)) {
                continue;
            }

            if ($context->to && $startUtc->gt($context->to)) {
                continue;
            }

            $title = $this->extractTitle($row, $text);
            if ($title === '') {
                continue;
            }

            $description = Str::limit($text, 800, '');
            $externalId = sha1($startUtc->format('Y-m-d') . '|' . $title);

            $items[] = new CandidateItem(
                title: $title,
                startsAtUtc: $startUtc,
                endsAtUtc: $endUtc,
                description: $description,
                sourceUrl: $url,
                externalId: $externalId,
                rawPayload: [
                    'row_text' => Str::limit($text, 2000, ''),
                ],
                eventType: null,
            );
        }

        return $items;
    }

    private function extractTitle(\DOMElement $row, string $fallbackText): string
    {
        $links = $row->getElementsByTagName('a');
        if ($links->length > 0) {
            $linked = trim((string) $links->item(0)?->textContent);
            if ($linked !== '') {
                return Str::limit($linked, 255, '');
            }
        }

        return Str::limit($fallbackText, 255, '');
    }

    /**
     * @return array{0:?CarbonImmutable,1:?CarbonImmutable}
     */
    private function extractDateRangeUtc(string $text, string $timezone): array
    {
        if (preg_match('/\b(\d{4}-\d{2}-\d{2})\s*(?:to|-)\s*(\d{4}-\d{2}-\d{2})\b/u', $text, $m) === 1) {
            $start = CarbonImmutable::parse($m[1], $timezone)->startOfDay()->utc();
            $end = CarbonImmutable::parse($m[2], $timezone)->endOfDay()->utc();
            return [$start, $end];
        }

        if (preg_match('/\b(\d{4}-\d{2}-\d{2})\b/u', $text, $m) === 1) {
            $start = CarbonImmutable::parse($m[1], $timezone)->startOfDay()->utc();
            return [$start, null];
        }

        return [null, null];
    }

    private function extractPrimaryYear(string $text): ?int
    {
        if (preg_match('/\b(\d{4})-\d{2}-\d{2}\b/u', $text, $m) === 1) {
            return (int) $m[1];
        }

        return null;
    }

    private function resolveSslVerifyOption(): bool|string
    {
        $caBundle = (string) config('events.crawler_ssl_ca_bundle', '');
        if ($caBundle !== '') {
            if (! is_file($caBundle)) {
                throw new DomainException("Go Astronomy crawler SSL: CA bundle file not found at '{$caBundle}'.");
            }

            return $caBundle;
        }

        $configuredVerify = (bool) config('events.crawler_ssl_verify', true);

        return app(SslVerificationPolicy::class)->resolveVerifyOption(
            allowInsecure: ! $configuredVerify
        );
    }
}
