<?php

namespace App\Services\Crawlers;

use App\Enums\EventSource;
use App\Services\Crawlers\Astropixels\AstropixelsYearUnavailableException;
use App\Support\Http\SslVerificationPolicy;
use App\Services\Crawlers\Astropixels\AstropixelsAlmanacParser;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

class AstropixelsCrawlerService implements CrawlerInterface
{
    private const REQUEST_TIMEOUT_SECONDS = 20;
    private const REQUEST_CONNECT_TIMEOUT_SECONDS = 10;
    private const REQUEST_HEADERS = [
        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'Accept-Language' => 'sk-SK,sk;q=0.9,en;q=0.8',
        'User-Agent' => 'AstrokomunitaCrawler/1.0 (+https://astropixels.com; research-use)',
    ];
    private const HUMANS_CHALLENGE_COOKIE_PATTERN = '/document\.cookie\s*=\s*"([A-Za-z0-9_]+=[^";]+)"/i';

    public function __construct(
        private readonly AstropixelsAlmanacParser $parser,
    ) {
    }

    public function source(): EventSource
    {
        return EventSource::ASTROPIXELS;
    }

    public function fetchCandidates(CrawlContext $context): CandidateBatch
    {
        $url = $this->buildUrlForYear($context->year);
        $verifyOption = $this->resolveSslVerifyOption();
        $sourceTimezone = $this->resolveSourceTimezone($context);

        $response = $this->fetchHtmlResponse($url, $verifyOption);

        try {
            $response->throw();
        } catch (RequestException $e) {
            $status = $e->response?->status() ?? null;
            if ($status === 404) {
                throw new AstropixelsYearUnavailableException(
                    year: $context->year,
                    url: $url,
                    previous: $e
                );
            }

            throw new DomainException(
                sprintf(
                    'ASTROPIXELS_HTTP_ERROR: GET %s failed with status %s.',
                    $url,
                    $status ?? 'n/a',
                ),
                previous: $e
            );
        }

        $html = (string) $response->body();

        try {
            $parseResult = $this->parser->parse($html, $context->year, $url, $sourceTimezone);
        } catch (Throwable $e) {
            throw new DomainException(
                'ASTROPIXELS_PARSE_ERROR: ' . $e->getMessage(),
                previous: $e
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
            source: EventSource::ASTROPIXELS,
            year: $context->year,
            fetchedAt: CarbonImmutable::now('UTC'),
            items: $items,
            fetchedBytes: strlen($html),
            sourceUrl: $url,
            headersUsed: self::REQUEST_HEADERS !== [],
            diagnostics: $parseResult->diagnostics,
        );
    }

    private function buildUrlForYear(int $year): string
    {
        $pattern = (string) config(
            'events.astropixels.base_url_pattern',
            'https://astropixels.com/almanac/almanac%2$02d/almanac%1$dcet.html'
        );
        $decadeFolderCode = $this->resolveAlmanacDecadeFolderCode($year);

        return sprintf($pattern, $year, $decadeFolderCode);
    }

    private function resolveAlmanacDecadeFolderCode(int $year): int
    {
        $normalizedYear = max(2001, $year);
        $decadeStartYear = 2001 + intdiv($normalizedYear - 2001, 10) * 10;

        return $decadeStartYear % 100;
    }

    private function resolveSourceTimezone(CrawlContext $context): string
    {
        $configured = (string) config('events.source_timezones.astropixels', '+01:00');
        $requested = trim($context->timezone);
        $genericDefault = (string) config('events.source_timezone', 'Europe/Bratislava');

        if ($requested === '' || $requested === $genericDefault) {
            return $configured;
        }

        return $requested;
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

        $configuredVerify = (bool) config('events.crawler_ssl_verify', true);

        return app(SslVerificationPolicy::class)->resolveVerifyOption(
            allowInsecure: ! $configuredVerify
        );
    }

    private function fetchHtmlResponse(string $url, bool|string $verifyOption): Response
    {
        $response = $this->requestHtml($url, $verifyOption);

        if ($response->status() !== 409) {
            return $response;
        }

        $challengeCookie = $this->extractHumansChallengeCookie((string) $response->body());
        if ($challengeCookie === null) {
            return $response;
        }

        return $this->requestHtml($url, $verifyOption, [
            'Cookie' => $challengeCookie,
        ]);
    }

    /**
     * @param array<string,string> $extraHeaders
     */
    private function requestHtml(string $url, bool|string $verifyOption, array $extraHeaders = []): Response
    {
        return Http::connectTimeout(self::REQUEST_CONNECT_TIMEOUT_SECONDS)
            ->timeout(self::REQUEST_TIMEOUT_SECONDS)
            ->withOptions(['verify' => $verifyOption])
            ->withAttributes(['ssl_verify' => $verifyOption])
            ->withHeaders(array_merge(self::REQUEST_HEADERS, $extraHeaders))
            ->get($url);
    }

    private function extractHumansChallengeCookie(string $body): ?string
    {
        if ($body === '' || ! str_contains($body, 'document.cookie')) {
            return null;
        }

        if (preg_match(self::HUMANS_CHALLENGE_COOKIE_PATTERN, $body, $matches) !== 1) {
            return null;
        }

        $cookie = trim((string) ($matches[1] ?? ''));

        return $cookie !== '' ? $cookie : null;
    }
}
