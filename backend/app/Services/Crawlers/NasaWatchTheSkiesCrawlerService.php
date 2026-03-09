<?php

namespace App\Services\Crawlers;

use App\Enums\EventSource;
use App\Support\Http\SslVerificationPolicy;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class NasaWatchTheSkiesCrawlerService implements CrawlerInterface
{
    private const REQUEST_TIMEOUT_SECONDS = 20;
    private const REQUEST_CONNECT_TIMEOUT_SECONDS = 10;
    private const REQUEST_RETRY_TIMES = 2;
    private const REQUEST_RETRY_SLEEP_MS = 500;
    private const MAX_DIAGNOSTICS = 60;

    private const REQUEST_HEADERS = [
        'Accept' => 'application/json',
        'User-Agent' => 'AstrokomunitaCrawler/1.0 (+https://aa.usno.navy.mil; research-use)',
    ];

    public function __construct(
        private readonly SslVerificationPolicy $sslVerificationPolicy,
    ) {
    }

    public function source(): EventSource
    {
        return EventSource::NASA_WATCH_THE_SKIES;
    }

    public function fetchCandidates(CrawlContext $context): CandidateBatch
    {
        $url = $this->resolveMoonPhasesYearUrl();
        $verifyOption = $this->resolveSslVerifyOption();
        $locationLabel = trim((string) config('events.nasa_watch_the_skies.location_label', 'Bratislava, SK'));

        $response = $this->requestJson($url, [
            'year' => $context->year,
        ], $verifyOption);

        if (! $response->successful()) {
            throw new DomainException(sprintf(
                'NASA_WTS_HTTP_ERROR: GET %s failed with status %s.',
                $url,
                $response->status()
            ));
        }

        $payload = $this->decodeJson((string) $response->body(), 'NASA_WTS_PARSE_ERROR: invalid moon phases payload.');
        $phaseRows = data_get($payload, 'phasedata');

        if (! is_array($phaseRows)) {
            throw new DomainException('NASA_WTS_PARSE_ERROR: missing "phasedata" list.');
        }

        $items = [];
        $diagnostics = [];

        foreach ($phaseRows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $year = (int) ($row['year'] ?? 0);
            $month = (int) ($row['month'] ?? 0);
            $day = (int) ($row['day'] ?? 0);
            $phaseName = trim((string) ($row['phase'] ?? ''));
            $time = trim((string) ($row['time'] ?? ''));

            if ($year !== $context->year || ! $this->isValidDate($year, $month, $day) || $phaseName === '') {
                continue;
            }

            $timeParts = $this->parseTime($time);
            if ($timeParts === null) {
                $this->addDiagnostic($diagnostics, sprintf('skip %04d-%02d-%02d: invalid phase time "%s"', $year, $month, $day, $time));
                continue;
            }

            $startsAtUtc = CarbonImmutable::create(
                $year,
                $month,
                $day,
                $timeParts['hour'],
                $timeParts['minute'],
                0,
                'UTC'
            );

            if ($context->from && $startsAtUtc->lt($context->from)) {
                continue;
            }
            if ($context->to && $startsAtUtc->gt($context->to)) {
                continue;
            }

            $title = $this->normalizePhaseTitle($phaseName);
            $date = sprintf('%04d-%02d-%02d', $year, $month, $day);

            $items[] = new CandidateItem(
                title: $title,
                startsAtUtc: $startsAtUtc,
                endsAtUtc: null,
                description: sprintf(
                    'Lunar phase from USNO API for %s. Visibility from Slovakia depends on local weather and horizon conditions.',
                    $locationLabel !== '' ? $locationLabel : 'Bratislava, SK'
                ),
                sourceUrl: $this->buildRequestUrl($url, ['year' => $context->year]),
                externalId: sprintf(
                    'usno-moon-phase:%s:%s',
                    $date,
                    Str::slug($title, '-')
                ),
                rawPayload: [
                    'date' => $date,
                    'time_utc' => $time,
                    'phase' => $phaseName,
                    'year_payload_row' => $row,
                ],
                eventType: 'observation_window',
                canonicalKey: null,
                confidenceScore: null,
                matchedSources: null,
                timeType: 'peak',
                timePrecision: 'exact',
            );
        }

        return new CandidateBatch(
            source: EventSource::NASA_WATCH_THE_SKIES,
            year: $context->year,
            fetchedAt: CarbonImmutable::now('UTC'),
            items: $items,
            fetchedBytes: strlen((string) $response->body()),
            sourceUrl: $this->buildRequestUrl($url, ['year' => $context->year]),
            headersUsed: self::REQUEST_HEADERS !== [],
            diagnostics: $diagnostics,
        );
    }

    /**
     * @param array<string,mixed> $query
     */
    private function requestJson(string $url, array $query, bool|string $verifyOption): Response
    {
        $attempts = self::REQUEST_RETRY_TIMES + 1;

        return Http::connectTimeout(self::REQUEST_CONNECT_TIMEOUT_SECONDS)
            ->timeout(self::REQUEST_TIMEOUT_SECONDS)
            ->retry($attempts, self::REQUEST_RETRY_SLEEP_MS, null, false)
            ->withOptions(['verify' => $verifyOption])
            ->withAttributes(['ssl_verify' => $verifyOption])
            ->withHeaders(self::REQUEST_HEADERS)
            ->get($url, $query);
    }

    /**
     * @return array<string,mixed>
     */
    private function decodeJson(string $raw, string $parseErrorMessage): array
    {
        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            throw new DomainException($parseErrorMessage);
        }

        return $decoded;
    }

    /**
     * @return array{hour:int,minute:int}|null
     */
    private function parseTime(string $value): ?array
    {
        if (preg_match('/^(?<hour>\d{1,2}):(?<minute>\d{2})$/', trim($value), $matches) !== 1) {
            return null;
        }

        $hour = (int) ($matches['hour'] ?? -1);
        $minute = (int) ($matches['minute'] ?? -1);

        if ($hour < 0 || $hour > 23 || $minute < 0 || $minute > 59) {
            return null;
        }

        return [
            'hour' => $hour,
            'minute' => $minute,
        ];
    }

    private function normalizePhaseTitle(string $phaseName): string
    {
        $normalized = trim(preg_replace('/\s+/u', ' ', $phaseName) ?? $phaseName);
        if ($normalized === '') {
            return 'Moon Phase';
        }

        if (str_contains(Str::lower($normalized), 'moon')) {
            return $normalized;
        }

        return $normalized . ' Moon';
    }

    private function resolveMoonPhasesYearUrl(): string
    {
        $url = trim((string) config(
            'events.nasa_watch_the_skies.moon_phases_year_url',
            config('events.nasa_watch_the_skies.url', 'https://aa.usno.navy.mil/api/moon/phases/year')
        ));

        return $url !== '' ? $url : 'https://aa.usno.navy.mil/api/moon/phases/year';
    }

    private function resolveSslVerifyOption(): bool|string
    {
        $caBundle = (string) config('events.crawler_ssl_ca_bundle', '');
        if ($caBundle !== '') {
            if (! is_file($caBundle)) {
                throw new DomainException("NASA WTS crawler SSL: CA bundle file not found at '{$caBundle}'.");
            }

            return $caBundle;
        }

        $configuredVerify = (bool) config('events.crawler_ssl_verify', true);

        return $this->sslVerificationPolicy->resolveVerifyOption(
            allowInsecure: ! $configuredVerify
        );
    }

    /**
     * @param array<string,mixed> $query
     */
    private function buildRequestUrl(string $url, array $query): string
    {
        $queryString = http_build_query($query);
        if ($queryString === '') {
            return $url;
        }

        return $url . (str_contains($url, '?') ? '&' : '?') . $queryString;
    }

    private function isValidDate(int $year, int $month, int $day): bool
    {
        return checkdate($month, $day, $year);
    }

    /**
     * @param array<int,string> $diagnostics
     */
    private function addDiagnostic(array &$diagnostics, string $message): void
    {
        if (count($diagnostics) >= self::MAX_DIAGNOSTICS) {
            return;
        }

        $normalized = trim(preg_replace('/\s+/u', ' ', $message) ?? '');
        if ($normalized === '') {
            return;
        }

        $diagnostics[] = Str::limit($normalized, 240, '');
    }
}
