<?php

namespace App\Services\Crawlers\Astropixels;

use App\Support\Http\SslVerificationPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class AstropixelsYearCatalogService
{
    private const REQUEST_TIMEOUT_SECONDS = 20;
    private const REQUEST_CONNECT_TIMEOUT_SECONDS = 10;
    private const CACHE_KEY = 'events:astropixels:catalog:v1';

    private const REQUEST_HEADERS = [
        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'Accept-Language' => 'sk-SK,sk;q=0.9,en;q=0.8',
        'User-Agent' => 'AstrokomunitaCrawler/1.0 (+https://astropixels.com; research-use)',
    ];

    public function __construct(
        private readonly SslVerificationPolicy $sslVerificationPolicy,
    ) {
    }

    /**
     * @return array{
     *   status:string,
     *   source_url:string,
     *   available_years:array<int,int>,
     *   checked_at:string,
     *   error:?string
     * }
     */
    public function snapshot(bool $forceRefresh = false): array
    {
        if (app()->environment('testing') && ! (bool) config('events.astropixels.catalog_fetch_during_tests', false)) {
            return $this->fallbackSnapshot('testing_fallback');
        }

        if ($forceRefresh) {
            Cache::forget(self::CACHE_KEY);
        }

        $ttlSeconds = max(60, (int) config('events.astropixels.catalog_cache_ttl_seconds', 21600));

        /** @var array{status:string,source_url:string,available_years:array<int,int>,checked_at:string,error:?string} $snapshot */
        $snapshot = Cache::remember(self::CACHE_KEY, now()->addSeconds($ttlSeconds), function (): array {
            return $this->fetchSnapshot();
        });

        return $snapshot;
    }

    /**
     * Returns:
     *  - `true`  if catalog confirms the year is published,
     *  - `false` if catalog confirms the year is not listed,
     *  - `null`  if catalog could not be loaded (unknown).
     */
    public function isYearAvailable(int $year): ?bool
    {
        $snapshot = $this->snapshot();

        if (($snapshot['status'] ?? '') !== 'ok') {
            return null;
        }

        $years = array_map('intval', (array) ($snapshot['available_years'] ?? []));

        return in_array($year, $years, true);
    }

    /**
     * @return array{
     *   status:string,
     *   source_url:string,
     *   available_years:array<int,int>,
     *   checked_at:string,
     *   error:?string
     * }
     */
    private function fetchSnapshot(): array
    {
        $sourceUrl = $this->resolveCatalogUrl();

        try {
            $response = Http::connectTimeout(self::REQUEST_CONNECT_TIMEOUT_SECONDS)
                ->timeout(self::REQUEST_TIMEOUT_SECONDS)
                ->withOptions(['verify' => $this->resolveSslVerifyOption()])
                ->withHeaders(self::REQUEST_HEADERS)
                ->get($sourceUrl);

            if (! $response->successful()) {
                return [
                    'status' => 'error',
                    'source_url' => $sourceUrl,
                    'available_years' => [],
                    'checked_at' => CarbonImmutable::now('UTC')->toISOString(),
                    'error' => sprintf('HTTP %d', $response->status()),
                ];
            }

            $years = $this->extractYears((string) $response->body());

            return [
                'status' => $years === [] ? 'empty' : 'ok',
                'source_url' => $sourceUrl,
                'available_years' => $years,
                'checked_at' => CarbonImmutable::now('UTC')->toISOString(),
                'error' => $years === [] ? 'No CET year links found in catalog.' : null,
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'error',
                'source_url' => $sourceUrl,
                'available_years' => [],
                'checked_at' => CarbonImmutable::now('UTC')->toISOString(),
                'error' => $this->truncate($e->getMessage(), 220),
            ];
        }
    }

    /**
     * @return array<int,int>
     */
    private function extractYears(string $html): array
    {
        if ($html === '') {
            return [];
        }

        preg_match_all('/almanac(?<year>20\d{2})cet\.html/i', $html, $matches);
        $years = array_map('intval', $matches['year'] ?? []);
        $years = array_values(array_unique($years));
        sort($years);

        $minYear = (int) config('events.astropixels.min_year', 2021);
        $maxYear = (int) config('events.astropixels.max_year', 2100);

        return array_values(array_filter($years, static fn (int $year): bool => $year >= $minYear && $year <= $maxYear));
    }

    private function resolveCatalogUrl(): string
    {
        $url = trim((string) config('events.astropixels.catalog_url', 'https://astropixels.com/almanac/almanac.html'));

        return $url !== '' ? $url : 'https://astropixels.com/almanac/almanac.html';
    }

    /**
     * @return array{
     *   status:string,
     *   source_url:string,
     *   available_years:array<int,int>,
     *   checked_at:string,
     *   error:?string
     * }
     */
    private function fallbackSnapshot(string $status): array
    {
        $minYear = (int) config('events.astropixels.min_year', 2021);
        $maxYear = (int) config('events.astropixels.max_year', 2100);
        $years = $minYear <= $maxYear ? range($minYear, $maxYear) : [];

        return [
            'status' => $status,
            'source_url' => $this->resolveCatalogUrl(),
            'available_years' => $years,
            'checked_at' => CarbonImmutable::now('UTC')->toISOString(),
            'error' => null,
        ];
    }

    private function resolveSslVerifyOption(): bool|string
    {
        $caBundle = (string) config('events.crawler_ssl_ca_bundle', '');
        if ($caBundle !== '') {
            if (is_file($caBundle)) {
                return $caBundle;
            }

            return true;
        }

        $configuredVerify = (bool) config('events.crawler_ssl_verify', true);

        return $this->sslVerificationPolicy->resolveVerifyOption(
            allowInsecure: ! $configuredVerify
        );
    }

    private function truncate(string $value, int $maxLength): string
    {
        if (mb_strlen($value) <= $maxLength) {
            return $value;
        }

        return mb_substr($value, 0, $maxLength);
    }
}
