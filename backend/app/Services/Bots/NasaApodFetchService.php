<?php

namespace App\Services\Bots;

use App\Models\BotSource;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class NasaApodFetchService
{
    /**
     * @return array<int, array{stable_key:string,payload:array<string,mixed>}>
     */
    public function fetch(BotSource $source): array
    {
        $payload = $this->fetchJson((string) $source->url);
        $row = $this->normalizePayload($payload);

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
        $timeoutSeconds = max(1, (int) config('astrobot.rss_timeout_seconds', 10));
        $retryTimes = max(0, (int) config('astrobot.rss_retry_times', 2));
        $retrySleepMs = max(0, (int) config('astrobot.rss_retry_sleep_ms', 250));
        $attempts = $retryTimes + 1;
        $apiKey = trim((string) config('services.nasa.apod_api_key', ''));

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
        $mediaType = strtolower(trim((string) ($payload['media_type'] ?? '')));
        $copyright = $this->normalizeText((string) ($payload['copyright'] ?? ''));
        $canonicalUrl = $hdurl !== '' ? $hdurl : $imageUrl;

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
}
