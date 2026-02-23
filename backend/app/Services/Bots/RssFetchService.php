<?php

namespace App\Services\Bots;

use App\Models\BotSource;
use App\Services\Bots\Exceptions\BotFetchException;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use SimpleXMLElement;

class RssFetchService
{
    /**
     * @return array<int, array{stable_key:string,payload:array<string,mixed>}>
     */
    public function fetch(BotSource $source): array
    {
        $xml = $this->fetchXml((string) $source->url);
        $items = $this->extractItems($xml);

        $normalized = [];
        foreach ($items as $item) {
            $row = $this->normalizeItem($item);
            if ($row === null) {
                continue;
            }

            $normalized[] = $row;
        }

        return $normalized;
    }

    private function fetchXml(string $url): SimpleXMLElement
    {
        $timeoutSeconds = max(1, (int) config('astrobot.rss_timeout_seconds', 10));
        $retryTimes = max(0, (int) config('astrobot.rss_retry_times', 2));
        $retrySleepMs = max(0, (int) config('astrobot.rss_retry_sleep_ms', 250));
        $attempts = $retryTimes + 1;

        try {
            $response = Http::secure()
                ->accept('application/rss+xml, application/xml, text/xml')
                ->timeout($timeoutSeconds)
                ->retry($attempts, $retrySleepMs, null, false)
                ->get($url);
        } catch (ConnectionException $e) {
            throw BotFetchException::forNetworkFailure($url, $e->getMessage());
        } catch (\Throwable $e) {
            throw BotFetchException::forNetworkFailure($url, $e->getMessage());
        }

        if (!$response->successful()) {
            throw BotFetchException::forHttpFailure($url, $response->status(), $response->body());
        }

        $contentType = (string) ($response->header('Content-Type') ?? '');
        if (!$this->isXmlContentType($contentType)) {
            throw BotFetchException::forInvalidContentType($url, $contentType, $response->body());
        }

        $body = (string) $response->body();
        if (!$this->looksLikeXml($body)) {
            throw BotFetchException::forInvalidXml($url, $body);
        }

        try {
            return $this->parseXml($body);
        } catch (RuntimeException $e) {
            throw BotFetchException::forInvalidXml($url, $body);
        }
    }

    private function parseXml(string $payload): SimpleXMLElement
    {
        libxml_use_internal_errors(true);

        $xml = simplexml_load_string($payload, SimpleXMLElement::class, LIBXML_NOCDATA);
        if ($xml !== false) {
            return $xml;
        }

        $dom = new \DOMDocument();
        if ($dom->loadXML($payload, LIBXML_NOCDATA)) {
            $imported = simplexml_import_dom($dom);
            if ($imported !== false) {
                return $imported;
            }
        }

        $errors = libxml_get_errors();
        libxml_clear_errors();
        $message = $errors[0]->message ?? 'Failed to parse RSS XML.';
        throw new RuntimeException(trim($message));
    }

    private function extractItems(SimpleXMLElement $xml): iterable
    {
        if (isset($xml->channel->item)) {
            return $xml->channel->item;
        }

        if (isset($xml->item)) {
            return $xml->item;
        }

        return [];
    }

    /**
     * @return array{stable_key:string,payload:array<string,mixed>}|null
     */
    private function normalizeItem(SimpleXMLElement $item): ?array
    {
        $title = $this->normalizeText((string) ($item->title ?? ''));
        $link = trim((string) ($item->link ?? ''));
        $guid = trim((string) ($item->guid ?? ''));
        $pubDateRaw = trim((string) ($item->pubDate ?? ''));

        $description = (string) ($item->description ?? '');
        if ($description === '' && isset($item->summary)) {
            $description = (string) $item->summary;
        }

        $summary = $this->buildSummary($description);
        $stableKey = $this->buildStableKey($guid, $link, (string) ($title ?? ''), $pubDateRaw);

        return [
            'stable_key' => $stableKey,
            'payload' => [
                'title' => $title ?? '',
                'summary' => $summary,
                'content' => $summary,
                'url' => $link !== '' ? $link : null,
                'published_at' => $this->parsePubDate($pubDateRaw),
                'fetched_at' => now(),
                'lang_original' => 'en',
                'meta' => [
                    'raw_guid' => $guid !== '' ? $guid : null,
                    'raw_pubDate' => $pubDateRaw !== '' ? $pubDateRaw : null,
                ],
            ],
        ];
    }

    private function buildStableKey(string $guid, string $link, string $title, string $pubDate): string
    {
        $candidate = trim($guid);
        if ($candidate === '') {
            $candidate = trim($link);
        }
        if ($candidate === '') {
            $candidate = sha1($title . '|' . $pubDate);
        }

        if (strlen($candidate) <= 191) {
            return $candidate;
        }

        return 'sha1:' . sha1($candidate);
    }

    private function buildSummary(string $html): ?string
    {
        $text = strip_tags($html);
        $text = $this->normalizeText($text);
        if ($text === null) {
            return null;
        }

        return $text;
    }

    private function parsePubDate(string $pubDate): ?Carbon
    {
        $value = trim($pubDate);
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
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

    private function isXmlContentType(string $contentType): bool
    {
        $type = strtolower(trim($contentType));
        if ($type === '') {
            // Some endpoints omit Content-Type; fallback to body sanity check.
            return true;
        }

        return str_contains($type, 'xml')
            || str_contains($type, 'rss')
            || str_contains($type, 'atom');
    }

    private function looksLikeXml(string $body): bool
    {
        $trimmed = ltrim($body);
        if ($trimmed === '') {
            return false;
        }

        return str_starts_with($trimmed, '<?xml')
            || str_starts_with($trimmed, '<rss')
            || str_starts_with($trimmed, '<feed')
            || str_starts_with($trimmed, '<channel');
    }
}
