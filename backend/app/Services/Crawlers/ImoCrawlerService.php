<?php

namespace App\Services\Crawlers;

use App\Enums\EventSource;
use App\Services\Crawlers\Imo\ImoParser;
use App\Support\Http\SslVerificationPolicy;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Throwable;

class ImoCrawlerService implements CrawlerInterface
{
    private const REQUEST_TIMEOUT_SECONDS = 20;
    private const REQUEST_CONNECT_TIMEOUT_SECONDS = 10;
    private const REQUEST_RETRY_TIMES = 2;
    private const REQUEST_RETRY_SLEEP_MS = 500;
    private const REQUEST_HEADERS = [
        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'Accept-Language' => 'en-US,en;q=0.9',
        'User-Agent' => 'AstrokomunitaCrawler/1.0 (+https://www.imo.net; research-use)',
    ];

    public function __construct(
        private readonly ImoParser $parser,
    ) {
    }

    public function source(): EventSource
    {
        return EventSource::IMO;
    }

    public function fetchCandidates(CrawlContext $context): CandidateBatch
    {
        $url = $this->resolveCalendarUrl();
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
        } catch (RequestException $exception) {
            throw new DomainException(
                sprintf(
                    'IMO_HTTP_ERROR: GET %s failed with status %s.',
                    $url,
                    $exception->response?->status() ?? 'n/a'
                ),
                previous: $exception
            );
        }

        $html = (string) $response->body();

        try {
            $parseResult = $this->parser->parse($html, $context->year, $url);
        } catch (Throwable $exception) {
            throw new DomainException(
                'IMO_PARSE_ERROR: ' . $exception->getMessage(),
                previous: $exception
            );
        }

        $items = $parseResult->items;

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
            source: EventSource::IMO,
            year: $context->year,
            fetchedAt: CarbonImmutable::now('UTC'),
            items: $items,
            fetchedBytes: strlen($html),
            sourceUrl: $url,
            headersUsed: self::REQUEST_HEADERS !== [],
            diagnostics: $parseResult->diagnostics,
        );
    }

    private function resolveCalendarUrl(): string
    {
        $url = trim((string) config('events.imo.url', 'https://www.imo.net/resources/calendar/'));
        return $url !== '' ? $url : 'https://www.imo.net/resources/calendar/';
    }

    private function resolveSslVerifyOption(): bool|string
    {
        $caBundle = (string) config('events.crawler_ssl_ca_bundle', '');
        if ($caBundle !== '') {
            if (! is_file($caBundle)) {
                throw new DomainException("IMO crawler SSL: CA bundle file not found at '{$caBundle}'.");
            }

            return $caBundle;
        }

        $configuredVerify = (bool) config('events.crawler_ssl_verify', true);

        return app(SslVerificationPolicy::class)->resolveVerifyOption(
            allowInsecure: ! $configuredVerify
        );
    }
}
