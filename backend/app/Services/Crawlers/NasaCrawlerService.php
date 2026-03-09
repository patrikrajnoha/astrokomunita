<?php

namespace App\Services\Crawlers;

use App\Enums\EventSource;
use App\Support\Http\SslVerificationPolicy;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class NasaCrawlerService implements CrawlerInterface
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
        return EventSource::NASA;
    }

    public function fetchCandidates(CrawlContext $context): CandidateBatch
    {
        $yearUrl = $this->resolveYearEndpointUrl();
        $dateUrl = $this->resolveDateEndpointUrl();
        $verifyOption = $this->resolveSslVerifyOption();
        $location = $this->resolveLocation();
        $includeOnlyVisible = (bool) config('events.nasa.include_only_visible', true);

        $diagnostics = [];

        $yearResponse = $this->requestJson($yearUrl, [
            'year' => $context->year,
        ], $verifyOption);

        if (! $yearResponse->successful()) {
            throw new DomainException(sprintf(
                'NASA_USNO_HTTP_ERROR: GET %s failed with status %s.',
                $yearUrl,
                $yearResponse->status()
            ));
        }

        $yearPayload = $this->decodeJson($yearResponse->body(), 'NASA_USNO_PARSE_ERROR: invalid yearly eclipse payload.');
        $yearRows = data_get($yearPayload, 'eclipses_in_year');

        if (! is_array($yearRows)) {
            throw new DomainException('NASA_USNO_PARSE_ERROR: missing "eclipses_in_year" list.');
        }

        $items = [];
        $fetchedBytes = strlen((string) $yearResponse->body());

        foreach ($yearRows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $rowYear = (int) ($row['year'] ?? 0);
            $rowMonth = (int) ($row['month'] ?? 0);
            $rowDay = (int) ($row['day'] ?? 0);

            if ($rowYear !== $context->year || ! $this->isValidDate($rowYear, $rowMonth, $rowDay)) {
                continue;
            }

            $date = sprintf('%04d-%02d-%02d', $rowYear, $rowMonth, $rowDay);
            $dateQuery = [
                'date' => $date,
                'coords' => sprintf('%s,%s', $location['lat'], $location['lon']),
                'height' => (string) $location['height_m'],
            ];

            $dateResponse = $this->requestJson($dateUrl, $dateQuery, $verifyOption);
            $fetchedBytes += strlen((string) $dateResponse->body());

            if (! $dateResponse->successful()) {
                $errorMessage = $this->extractErrorMessage((string) $dateResponse->body());
                $errorKey = Str::lower($errorMessage);

                if ($includeOnlyVisible && str_contains($errorKey, 'not visible')) {
                    $this->addDiagnostic($diagnostics, sprintf('skip %s: not visible for configured location', $date));
                    continue;
                }

                throw new DomainException(sprintf(
                    'NASA_USNO_HTTP_ERROR: GET %s failed for %s with status %s (%s).',
                    $dateUrl,
                    $date,
                    $dateResponse->status(),
                    $errorMessage
                ));
            }

            $datePayload = $this->decodeJson(
                (string) $dateResponse->body(),
                sprintf('NASA_USNO_PARSE_ERROR: invalid eclipse payload for %s.', $date)
            );

            $candidate = $this->buildCandidateItem(
                date: $date,
                rowPayload: $row,
                localPayload: $datePayload,
                requestUrl: $this->buildRequestUrl($dateUrl, $dateQuery),
                location: $location,
            );

            if ($candidate === null) {
                $this->addDiagnostic($diagnostics, sprintf('skip %s: missing essential eclipse fields', $date));
                continue;
            }

            if ($context->from && $candidate->startsAtUtc->lt($context->from)) {
                continue;
            }
            if ($context->to && $candidate->startsAtUtc->gt($context->to)) {
                continue;
            }

            $items[] = $candidate;
        }

        return new CandidateBatch(
            source: EventSource::NASA,
            year: $context->year,
            fetchedAt: CarbonImmutable::now('UTC'),
            items: $items,
            fetchedBytes: $fetchedBytes,
            sourceUrl: $this->buildRequestUrl($yearUrl, ['year' => $context->year]),
            headersUsed: self::REQUEST_HEADERS !== [],
            diagnostics: $diagnostics,
        );
    }

    /**
     * @param array<string,mixed> $rowPayload
     * @param array<string,mixed> $localPayload
     * @param array{lat:string,lon:string,height_m:int,label:string} $location
     */
    private function buildCandidateItem(
        string $date,
        array $rowPayload,
        array $localPayload,
        string $requestUrl,
        array $location,
    ): ?CandidateItem {
        $localProperties = data_get($localPayload, 'properties', []);
        if (! is_array($localProperties)) {
            return null;
        }

        $eventTitle = trim((string) ($localProperties['event'] ?? $rowPayload['event'] ?? ''));
        if ($eventTitle === '') {
            $eventTitle = sprintf('Solar Eclipse on %s', $date);
        }

        $descriptionParts = [
            sprintf('Local visibility for %s from USNO API.', $location['label']),
            $this->nullableText((string) ($localProperties['description'] ?? null)),
        ];

        $magnitude = $this->nullableText((string) ($localProperties['magnitude'] ?? null));
        if ($magnitude !== null) {
            $descriptionParts[] = sprintf('Magnitude: %s.', $magnitude);
        }

        $obscuration = $this->nullableText((string) ($localProperties['obscuration'] ?? null));
        if ($obscuration !== null) {
            $descriptionParts[] = sprintf('Obscuration: %s.', $obscuration);
        }

        $duration = $this->nullableText((string) ($localProperties['duration'] ?? null));
        if ($duration !== null) {
            $descriptionParts[] = sprintf('Duration: %s.', $duration);
        }

        $timing = $this->resolveTiming($date, data_get($localProperties, 'local_data', []));
        $startsAtUtc = $timing['starts_at'];
        $timePrecision = $timing['is_exact'] ? 'exact' : 'approximate';

        $description = implode(' ', array_values(array_filter($descriptionParts, static fn (?string $part): bool => $part !== null)));

        return new CandidateItem(
            title: $eventTitle,
            startsAtUtc: $startsAtUtc,
            endsAtUtc: null,
            description: $description !== '' ? $description : null,
            sourceUrl: $requestUrl,
            externalId: sprintf(
                'usno-solar-eclipse:%s:%s',
                $date,
                substr(sha1($location['lat'] . '|' . $location['lon'] . '|' . $location['height_m']), 0, 10)
            ),
            rawPayload: [
                'date' => $date,
                'location' => $location,
                'year_row' => $rowPayload,
                'local_properties' => $localProperties,
            ],
            eventType: 'eclipse_solar',
            canonicalKey: null,
            confidenceScore: null,
            matchedSources: null,
            timeType: 'start',
            timePrecision: $timePrecision,
        );
    }

    /**
     * @param mixed $localData
     * @return array{starts_at:CarbonImmutable,is_exact:bool}
     */
    private function resolveTiming(string $date, mixed $localData): array
    {
        $fallback = CarbonImmutable::parse($date, 'UTC')->setTime(12, 0, 0);

        if (! is_array($localData)) {
            return ['starts_at' => $fallback, 'is_exact' => false];
        }

        $bestEntry = null;

        foreach ($localData as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $phenomenon = Str::lower(trim((string) ($entry['phenomenon'] ?? '')));
            if ($phenomenon === '') {
                continue;
            }

            if (str_contains($phenomenon, 'eclipse begins')) {
                $bestEntry = $entry;
                break;
            }

            if ($bestEntry === null && str_contains($phenomenon, 'maximum')) {
                $bestEntry = $entry;
            }
        }

        if ($bestEntry === null) {
            foreach ($localData as $entry) {
                if (! is_array($entry)) {
                    continue;
                }

                $time = trim((string) ($entry['time'] ?? ''));
                if ($this->parseTime($time) !== null) {
                    $bestEntry = $entry;
                    break;
                }
            }
        }

        if ($bestEntry === null) {
            return ['starts_at' => $fallback, 'is_exact' => false];
        }

        $timeParts = $this->parseTime((string) ($bestEntry['time'] ?? ''));
        if ($timeParts === null) {
            return ['starts_at' => $fallback, 'is_exact' => false];
        }

        $day = (int) ($bestEntry['day'] ?? substr($date, 8, 2));
        $baseDate = CarbonImmutable::parse($date, 'UTC');

        if ($day < 1 || $day > 31) {
            $day = (int) $baseDate->format('d');
        }

        $startsAt = CarbonImmutable::create(
            (int) $baseDate->format('Y'),
            (int) $baseDate->format('m'),
            $day,
            $timeParts['hour'],
            $timeParts['minute'],
            $timeParts['second'],
            'UTC'
        );

        return ['starts_at' => $startsAt, 'is_exact' => true];
    }

    /**
     * @return array{hour:int,minute:int,second:int}|null
     */
    private function parseTime(string $value): ?array
    {
        if (preg_match('/^(?<hour>\d{1,2}):(?<minute>\d{2})(?::(?<second>\d{2})(?:\.\d+)?)?$/', trim($value), $matches) !== 1) {
            return null;
        }

        $hour = (int) ($matches['hour'] ?? 0);
        $minute = (int) ($matches['minute'] ?? 0);
        $second = (int) ($matches['second'] ?? 0);

        if ($hour < 0 || $hour > 23 || $minute < 0 || $minute > 59 || $second < 0 || $second > 59) {
            return null;
        }

        return [
            'hour' => $hour,
            'minute' => $minute,
            'second' => $second,
        ];
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

    private function extractErrorMessage(string $body): string
    {
        $decoded = json_decode($body, true);
        if (is_array($decoded)) {
            $message = trim((string) ($decoded['error'] ?? $decoded['message'] ?? ''));
            if ($message !== '') {
                return $message;
            }
        }

        $normalized = trim(preg_replace('/\s+/u', ' ', $body) ?? '');
        if ($normalized === '') {
            return 'n/a';
        }

        return function_exists('mb_substr')
            ? mb_substr($normalized, 0, 240)
            : substr($normalized, 0, 240);
    }

    private function resolveYearEndpointUrl(): string
    {
        $url = trim((string) config('events.nasa.eclipses_year_url', 'https://aa.usno.navy.mil/api/eclipses/solar/year'));

        return $url !== '' ? $url : 'https://aa.usno.navy.mil/api/eclipses/solar/year';
    }

    private function resolveDateEndpointUrl(): string
    {
        $url = trim((string) config('events.nasa.eclipse_date_url', 'https://aa.usno.navy.mil/api/eclipses/solar/date'));

        return $url !== '' ? $url : 'https://aa.usno.navy.mil/api/eclipses/solar/date';
    }

    /**
     * @return array{lat:string,lon:string,height_m:int,label:string}
     */
    private function resolveLocation(): array
    {
        $lat = (float) config('events.nasa.location.lat', 48.1486);
        $lon = (float) config('events.nasa.location.lon', 17.1077);
        $heightM = max(0, (int) config('events.nasa.location.height_m', 150));
        $label = trim((string) config('events.nasa.location.label', 'Bratislava, SK'));

        return [
            'lat' => rtrim(rtrim(number_format($lat, 6, '.', ''), '0'), '.'),
            'lon' => rtrim(rtrim(number_format($lon, 6, '.', ''), '0'), '.'),
            'height_m' => $heightM,
            'label' => $label !== '' ? $label : 'Bratislava, SK',
        ];
    }

    private function resolveSslVerifyOption(): bool|string
    {
        $caBundle = (string) config('events.crawler_ssl_ca_bundle', '');
        if ($caBundle !== '') {
            if (! is_file($caBundle)) {
                throw new DomainException("NASA crawler SSL: CA bundle file not found at '{$caBundle}'.");
            }

            return $caBundle;
        }

        $configuredVerify = (bool) config('events.crawler_ssl_verify', true);

        return $this->sslVerificationPolicy->resolveVerifyOption(
            allowInsecure: ! $configuredVerify
        );
    }

    private function nullableText(?string $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
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
