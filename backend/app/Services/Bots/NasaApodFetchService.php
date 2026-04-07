<?php

namespace App\Services\Bots;

use App\Models\BotSource;
use App\Services\Bots\Exceptions\BotSourceRunException;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use SimpleXMLElement;

class NasaApodFetchService
{
    /**
     * @return array<int, array{stable_key:string,payload:array<string,mixed>}>
     */
    public function fetch(BotSource $source): array
    {
        try {
            $payload = $this->fetchJson((string) $source->url);
            $row = $this->normalizePayload($payload);
        } catch (BotSourceRunException $exception) {
            if (!$this->canFallbackToRss($exception)) {
                throw $exception;
            }

            $fallbackRow = $this->fetchFromRssFallback($exception->failureReason());
            if ($fallbackRow === null) {
                throw $exception;
            }

            $row = $fallbackRow;
        }

        if ($row === null) {
            return [];
        }

        return [$row];
    }

    /**
     * @return array<string,mixed>
     */
    private function fetchJson(string $url): array
    {
        $timeoutSeconds = max(1, (int) config('bots.rss_timeout_seconds', 10));
        $retryTimes = max(0, (int) config('bots.rss_retry_times', 2));
        $retrySleepMs = max(0, (int) config('bots.rss_retry_sleep_ms', 250));
        $attempts = $retryTimes + 1;
        $apiKey = trim((string) config('services.nasa.key', config('services.nasa.apod_api_key', '')));
        $requiresApiKey = (bool) config('bots.sources.nasa_apod_daily.requires_api_key', true);

        if ($requiresApiKey && $apiKey === '') {
            throw new BotSourceRunException(
                'NASA APOD API vyzaduje API kluc. Pridajte NASA_API_KEY alebo pockajte.',
                'needs_api_key',
                [
                    'provider' => 'nasa_apod',
                    'http_status' => null,
                    'message' => 'NASA APOD API vyzaduje API kluc. Pridajte NASA_API_KEY alebo pockajte.',
                ]
            );
        }

        $query = [];
        if ($apiKey !== '') {
            $query['api_key'] = $apiKey;
        }

        try {
            $response = Http::secure()
                ->acceptJson()
                ->timeout($timeoutSeconds)
                ->retry($attempts, $retrySleepMs, null, false)
                ->get($url, $query);
        } catch (ConnectionException $e) {
            throw new RuntimeException(sprintf(
                'APOD fetch failed (url=%s, status=network_error, snippet="%s")',
                $url,
                $this->snippet($e->getMessage())
            ));
        } catch (\Throwable $e) {
            throw new RuntimeException(sprintf(
                'APOD fetch failed (url=%s, status=network_error, snippet="%s")',
                $url,
                $this->snippet($e->getMessage())
            ));
        }

        if (!$response->successful()) {
            if ($response->status() === 429) {
                throw new BotSourceRunException(
                    'NASA APOD API limit poziadaviek (HTTP 429). Pridajte NASA_API_KEY alebo skuste neskor.',
                    'rate_limited',
                    [
                        'provider' => 'nasa_apod',
                        'http_status' => 429,
                        'message' => 'NASA APOD API limit poziadaviek (HTTP 429). Pridajte NASA_API_KEY alebo skuste neskor.',
                        'retry_after_sec' => $this->resolveRetryAfterSeconds($response),
                    ]
                );
            }

            throw new RuntimeException(sprintf(
                'APOD fetch failed (url=%s, status=%d, snippet="%s")',
                $url,
                $response->status(),
                $this->snippet((string) $response->body())
            ));
        }

        $json = $response->json();
        if (!is_array($json)) {
            throw new RuntimeException(sprintf(
                'APOD fetch failed (url=%s, status=invalid_json, snippet="%s")',
                $url,
                $this->snippet((string) $response->body())
            ));
        }

        return $json;
    }

    private function canFallbackToRss(BotSourceRunException $exception): bool
    {
        if (!$this->isRssFallbackEnabled()) {
            return false;
        }

        $reason = strtolower(trim($exception->failureReason()));

        return in_array($reason, ['rate_limited', 'needs_api_key'], true);
    }

    /**
     * @return array{stable_key:string,payload:array<string,mixed>}|null
     */
    private function fetchFromRssFallback(string $failureReason): ?array
    {
        $fallbackUrl = $this->resolveRssFallbackUrl();
        if ($fallbackUrl === '') {
            return null;
        }

        $timeoutSeconds = max(1, (int) config('bots.rss_timeout_seconds', 10));
        $retryTimes = max(0, (int) config('bots.rss_retry_times', 2));
        $retrySleepMs = max(0, (int) config('bots.rss_retry_sleep_ms', 250));
        $attempts = $retryTimes + 1;

        try {
            $response = Http::secure()
                ->accept('application/rss+xml, application/xml, text/xml')
                ->timeout($timeoutSeconds)
                ->retry($attempts, $retrySleepMs, null, false)
                ->get($fallbackUrl);
        } catch (\Throwable) {
            return null;
        }

        if (!$response->successful()) {
            return null;
        }

        try {
            $xml = $this->parseRssXml((string) $response->body());
            $item = $this->extractFirstRssItem($xml);
        } catch (\Throwable) {
            return null;
        }

        if (!$item) {
            return null;
        }

        return $this->normalizeRssFallbackItem($item, strtolower(trim($failureReason)));
    }

    private function isRssFallbackEnabled(): bool
    {
        return (bool) config('bots.sources.nasa_apod_daily.enable_rss_fallback', true);
    }

    private function resolveRssFallbackUrl(): string
    {
        return trim((string) config(
            'bots.sources.nasa_apod_daily.rss_fallback_url',
            'https://apod.nasa.gov/apod.rss'
        ));
    }

    private function parseRssXml(string $payload): SimpleXMLElement
    {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($payload, SimpleXMLElement::class, LIBXML_NOCDATA);

        if ($xml !== false) {
            return $xml;
        }

        $errors = libxml_get_errors();
        libxml_clear_errors();
        $message = trim((string) ($errors[0]->message ?? 'Failed to parse APOD RSS XML.'));
        throw new RuntimeException($message !== '' ? $message : 'Failed to parse APOD RSS XML.');
    }

    private function extractFirstRssItem(SimpleXMLElement $xml): ?SimpleXMLElement
    {
        $items = null;

        if (isset($xml->channel->item)) {
            $items = $xml->channel->item;
        } elseif (isset($xml->item)) {
            $items = $xml->item;
        }

        if (!$items) {
            return null;
        }

        $first = $items[0] ?? null;

        return $first instanceof SimpleXMLElement ? $first : null;
    }

    /**
     * @return array{stable_key:string,payload:array<string,mixed>}|null
     */
    private function normalizeRssFallbackItem(SimpleXMLElement $item, string $failureReason): ?array
    {
        $title = $this->normalizeText((string) ($item->title ?? ''));
        $link = trim((string) ($item->link ?? ''));
        $description = (string) ($item->description ?? '');
        $contentEncoded = $this->extractRssContentEncoded($item);
        $textSource = $contentEncoded !== '' ? $contentEncoded : $description;
        $content = $this->normalizeText(strip_tags($textSource));
        $imageUrl = $this->extractRssImageUrl($item, $contentEncoded, $description);
        $imageUrl = $this->promoteRssFallbackImageUrl($imageUrl, $link);
        $apodDate = $this->extractApodDateFromRss($item, $link);
        $canonicalUrl = $link !== '' ? $link : $imageUrl;

        if ($title === null && $content === null && $canonicalUrl === '') {
            return null;
        }

        $mediaType = $imageUrl !== '' ? 'image' : 'video';
        $videoUrl = $mediaType === 'video' && $canonicalUrl !== '' ? $canonicalUrl : null;
        $publishedAt = $this->parseDate(
            $apodDate !== '' ? $apodDate : trim((string) ($item->pubDate ?? ''))
        );

        return [
            'stable_key' => $this->buildStableKey($apodDate, $canonicalUrl),
            'payload' => [
                'title' => $title ?? '',
                'summary' => $content,
                'content' => $content,
                'url' => $canonicalUrl !== '' ? $canonicalUrl : null,
                'published_at' => $publishedAt,
                'fetched_at' => now(),
                'lang_original' => 'en',
                'meta' => [
                    'apod_date' => $apodDate !== '' ? $apodDate : null,
                    'image_url' => $imageUrl !== '' ? $imageUrl : null,
                    'hdurl' => $imageUrl !== '' ? $imageUrl : null,
                    'media_type' => $mediaType,
                    'video_url' => $videoUrl,
                    'thumbnail_url' => $imageUrl !== '' ? $imageUrl : null,
                    'copyright' => null,
                    'fallback_source' => 'apod_rss',
                    'fallback_reason' => $failureReason,
                ],
            ],
        ];
    }

    private function extractRssContentEncoded(SimpleXMLElement $item): string
    {
        try {
            $content = $item->children('content', true);
            return (string) ($content->encoded ?? '');
        } catch (\Throwable) {
            return '';
        }
    }

    private function extractRssImageUrl(SimpleXMLElement $item, string $contentEncoded, string $description): string
    {
        try {
            $media = $item->children('media', true);
            if (isset($media->content)) {
                $attrs = $media->content->attributes();
                $url = isset($attrs['url']) ? trim((string) $attrs['url']) : '';
                if ($url !== '') {
                    return $url;
                }
            }
            if (isset($media->thumbnail)) {
                $attrs = $media->thumbnail->attributes();
                $url = isset($attrs['url']) ? trim((string) $attrs['url']) : '';
                if ($url !== '') {
                    return $url;
                }
            }
        } catch (\Throwable) {
            // ignore
        }

        try {
            if (isset($item->enclosure)) {
                $attrs = $item->enclosure->attributes();
                $url = isset($attrs['url']) ? trim((string) $attrs['url']) : '';
                if ($url !== '') {
                    return $url;
                }
            }
        } catch (\Throwable) {
            // ignore
        }

        $html = $contentEncoded !== '' ? $contentEncoded : $description;
        if ($html !== '' && preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $html, $matches)) {
            $url = trim((string) ($matches[1] ?? ''));
            if ($url !== '') {
                return $url;
            }
        }

        return '';
    }

    private function promoteRssFallbackImageUrl(string $imageUrl, string $articleLink): string
    {
        $baseArticleUrl = $this->toAbsoluteUrl($articleLink, 'https://apod.nasa.gov/apod/astropix.html');
        $normalizedImageUrl = $this->toAbsoluteUrl($imageUrl, $baseArticleUrl !== '' ? $baseArticleUrl : 'https://apod.nasa.gov/apod/');

        if ($baseArticleUrl === '') {
            return $normalizedImageUrl;
        }

        $articleImageUrl = $this->extractImageUrlFromApodArticle($baseArticleUrl);
        if ($articleImageUrl === '') {
            return $normalizedImageUrl;
        }

        if ($normalizedImageUrl === '') {
            return $articleImageUrl;
        }

        if ($this->isApodCalendarThumbnail($normalizedImageUrl) && !$this->isApodCalendarThumbnail($articleImageUrl)) {
            return $articleImageUrl;
        }

        return $normalizedImageUrl;
    }

    private function extractImageUrlFromApodArticle(string $articleUrl): string
    {
        $timeoutSeconds = max(1, (int) config('bots.rss_timeout_seconds', 10));
        $retryTimes = max(0, (int) config('bots.rss_retry_times', 2));
        $retrySleepMs = max(0, (int) config('bots.rss_retry_sleep_ms', 250));
        $attempts = $retryTimes + 1;

        try {
            $response = Http::secure()
                ->accept('text/html,application/xhtml+xml,*/*;q=0.8')
                ->timeout($timeoutSeconds)
                ->retry($attempts, $retrySleepMs, null, false)
                ->get($articleUrl);
        } catch (\Throwable) {
            return '';
        }

        if (!$response->successful()) {
            return '';
        }

        $html = (string) $response->body();
        if (trim($html) === '') {
            return '';
        }

        $best = $this->findBestImageCandidateFromHtml($html, '/<a[^>]+href=["\']([^"\']+)["\']/i', $articleUrl);
        if ($best !== '') {
            return $best;
        }

        return $this->findBestImageCandidateFromHtml($html, '/<img[^>]+src=["\']([^"\']+)["\']/i', $articleUrl);
    }

    private function findBestImageCandidateFromHtml(string $html, string $pattern, string $baseUrl): string
    {
        preg_match_all($pattern, $html, $matches);
        $rawCandidates = is_array($matches[1] ?? null) ? $matches[1] : [];
        $candidates = [];

        foreach ($rawCandidates as $raw) {
            $candidate = $this->toAbsoluteUrl((string) $raw, $baseUrl);
            if ($candidate === '' || !$this->isLikelyImageUrl($candidate)) {
                continue;
            }

            $candidates[] = $candidate;
        }

        if ($candidates === []) {
            return '';
        }

        foreach ($candidates as $candidate) {
            if (str_contains(strtolower($candidate), '/image/') && !$this->isApodCalendarThumbnail($candidate)) {
                return $candidate;
            }
        }

        foreach ($candidates as $candidate) {
            if (!$this->isApodCalendarThumbnail($candidate)) {
                return $candidate;
            }
        }

        return $candidates[0];
    }

    private function toAbsoluteUrl(string $value, string $baseUrl): string
    {
        $url = trim($value);
        if ($url === '') {
            return '';
        }

        if (preg_match('/^https?:\/\//i', $url) === 1) {
            return $url;
        }

        if (str_starts_with($url, '//')) {
            return 'https:' . $url;
        }

        $base = trim($baseUrl);
        if ($base === '') {
            return '';
        }

        $scheme = (string) (parse_url($base, PHP_URL_SCHEME) ?: 'https');
        $host = (string) (parse_url($base, PHP_URL_HOST) ?: '');
        if ($host === '') {
            return '';
        }

        if (str_starts_with($url, '/')) {
            return sprintf('%s://%s%s', $scheme, $host, $url);
        }

        $basePath = (string) (parse_url($base, PHP_URL_PATH) ?: '/');
        if (!str_ends_with($basePath, '/')) {
            $basePath = dirname($basePath);
        }
        if ($basePath === '.' || $basePath === '\\') {
            $basePath = '/';
        }

        $basePath = '/' . trim(str_replace('\\', '/', $basePath), '/');
        if (!str_ends_with($basePath, '/')) {
            $basePath .= '/';
        }

        return sprintf('%s://%s%s%s', $scheme, $host, $basePath, ltrim($url, '/'));
    }

    private function isLikelyImageUrl(string $url): bool
    {
        $path = strtolower(trim((string) parse_url($url, PHP_URL_PATH)));
        if ($path === '') {
            return false;
        }

        $extension = strtolower(trim((string) pathinfo($path, PATHINFO_EXTENSION)));
        if ($extension === '') {
            return false;
        }

        return in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true);
    }

    private function isApodCalendarThumbnail(string $url): bool
    {
        $path = strtolower(trim((string) parse_url($url, PHP_URL_PATH)));
        if ($path === '') {
            return false;
        }

        if (str_contains($path, '/calendar/')) {
            return true;
        }

        return preg_match('/\/s_[0-9]{6}\.(jpg|jpeg|png|webp|gif)$/i', $path) === 1;
    }

    private function extractApodDateFromRss(SimpleXMLElement $item, string $link): string
    {
        if ($link !== '' && preg_match('/ap(\d{2})(\d{2})(\d{2})\.html/i', $link, $matches)) {
            $year = $this->convertTwoDigitYearToFourDigits((int) $matches[1]);
            $month = max(1, min(12, (int) $matches[2]));
            $day = max(1, min(31, (int) $matches[3]));

            return sprintf('%04d-%02d-%02d', $year, $month, $day);
        }

        $pubDate = trim((string) ($item->pubDate ?? ''));
        if ($pubDate !== '') {
            try {
                return Carbon::parse($pubDate)->toDateString();
            } catch (\Throwable) {
                // ignore
            }
        }

        return '';
    }

    private function convertTwoDigitYearToFourDigits(int $year): int
    {
        if ($year >= 95) {
            return 1900 + $year;
        }

        return 2000 + $year;
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{stable_key:string,payload:array<string,mixed>}|null
     */
    private function normalizePayload(array $payload): ?array
    {
        $date = trim((string) ($payload['date'] ?? ''));
        $title = $this->normalizeText((string) ($payload['title'] ?? ''));
        $content = $this->normalizeText((string) ($payload['explanation'] ?? ''));
        $imageUrl = trim((string) ($payload['url'] ?? ''));
        $hdurl = trim((string) ($payload['hdurl'] ?? ''));
        $thumbnailUrl = trim((string) ($payload['thumbnail_url'] ?? ''));
        $mediaType = strtolower(trim((string) ($payload['media_type'] ?? '')));
        $copyright = $this->normalizeText((string) ($payload['copyright'] ?? ''));
        $canonicalUrl = $hdurl !== '' ? $hdurl : $imageUrl;
        $videoUrl = $mediaType === 'video'
            ? ($canonicalUrl !== '' ? $canonicalUrl : null)
            : null;

        if ($date === '' && $canonicalUrl === '' && $title === null && $content === null) {
            return null;
        }

        return [
            'stable_key' => $this->buildStableKey($date, $canonicalUrl),
            'payload' => [
                'title' => $title ?? '',
                'summary' => $content,
                'content' => $content,
                'url' => $canonicalUrl !== '' ? $canonicalUrl : null,
                'published_at' => $this->parseDate($date),
                'fetched_at' => now(),
                'lang_original' => 'en',
                'meta' => [
                    'apod_date' => $date !== '' ? $date : null,
                    'image_url' => $imageUrl !== '' ? $imageUrl : null,
                    'hdurl' => $hdurl !== '' ? $hdurl : null,
                    'media_type' => $mediaType !== '' ? $mediaType : null,
                    'video_url' => $videoUrl,
                    'thumbnail_url' => $thumbnailUrl !== '' ? $thumbnailUrl : null,
                    'copyright' => $copyright,
                ],
            ],
        ];
    }

    private function buildStableKey(string $date, string $url): string
    {
        if ($date !== '' && strlen($date) <= 191) {
            return $date;
        }

        return 'sha1:' . sha1($date . '|' . $url);
    }

    private function parseDate(string $date): ?Carbon
    {
        $value = trim($date);
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizeText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $s = trim($value);
        if ($s === '') {
            return null;
        }

        $s = str_replace(['&amp;#', '&amp;nbsp;'], ['&#', ' '], $s);
        $s = preg_replace('/&#(\d+)(?!;)/', '&#$1;', $s) ?? $s;
        $s = preg_replace('/&#x([0-9a-fA-F]+)(?!;)/', '&#x$1;', $s) ?? $s;

        for ($i = 0; $i < 2; $i++) {
            $decoded = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($decoded === $s) {
                break;
            }

            $s = $decoded;
        }

        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
        $s = trim($s);

        return $s !== '' ? $s : null;
    }

    private function snippet(string $value): string
    {
        $normalized = preg_replace('/\s+/u', ' ', trim($value)) ?? '';
        if ($normalized === '') {
            return 'n/a';
        }

        if (function_exists('mb_substr')) {
            return mb_substr($normalized, 0, 500);
        }

        return substr($normalized, 0, 500);
    }

    private function resolveRetryAfterSeconds(Response $response): ?int
    {
        $retryAfterRaw = trim((string) $response->header('Retry-After', ''));
        if ($retryAfterRaw === '') {
            return null;
        }

        if (is_numeric($retryAfterRaw)) {
            $seconds = (int) $retryAfterRaw;
            return $seconds > 0 ? $seconds : null;
        }

        try {
            $retryAt = Carbon::parse($retryAfterRaw);
            $seconds = now()->diffInSeconds($retryAt, false);
            return $seconds > 0 ? $seconds : null;
        } catch (\Throwable) {
            return null;
        }
    }
}

