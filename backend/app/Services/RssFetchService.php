<?php

namespace App\Services;

use App\Models\RssItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use SimpleXMLElement;

class RssFetchService
{
    public const SOURCE_NASA_NEWS = 'nasa_news';
    public const NASA_NEWS_FEED_URL = 'https://www.nasa.gov/news-release/feed/';

    /**
     * Fetch RSS from given source and store new items as pending.
     *
     * @param string $source
     * @param string|null $feedUrl
     * @return array{created:int, skipped:int, errors:int}
     */
    public function fetch(string $source = self::SOURCE_NASA_NEWS, ?string $feedUrl = null): array
    {
        $feedUrl ??= match ($source) {
            self::SOURCE_NASA_NEWS => self::NASA_NEWS_FEED_URL,
            default => throw new \InvalidArgumentException("Unsupported source: $source"),
        };

        $xml = $this->fetchXml($feedUrl);
        $items = $this->extractItems($xml);

        $created = 0;
        $skipped = 0;
        $errors = 0;

        $now = now();

        foreach ($items as $item) {
            try {
                $title = $this->normalizeText((string) ($item->title ?? ''));
                $link = trim((string) ($item->link ?? ''));
                $guid = trim((string) ($item->guid ?? ''));
                $description = (string) ($item->description ?? '');
                $pubDate = (string) ($item->pubDate ?? '');

                if ($title === '' || $link === '') {
                    $errors++;
                    continue;
                }

                $summary = $this->buildSummary($description);
                $publishedAt = $this->parsePubDate($pubDate);

                // Deduplication: prefer guid, fallback to url hash
                $dedupeHash = $guid !== '' ? sha1($source . ':' . $guid) : sha1(strtolower($link));

                // Check if already exists
                $exists = RssItem::where('dedupe_hash', $dedupeHash)->exists();
                if ($exists) {
                    $skipped++;
                    continue;
                }

                RssItem::create([
                    'source' => $source,
                    'guid' => $guid !== '' ? $guid : null,
                    'url' => $link,
                    'dedupe_hash' => $dedupeHash,
                    'title' => $title,
                    'summary' => $summary,
                    'published_at' => $publishedAt,
                    'fetched_at' => $now,
                    'status' => RssItem::STATUS_PENDING,
                ]);

                $created++;
            } catch (\Throwable $e) {
                $errors++;
                continue;
            }
        }

        return [
            'created' => $created,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    private function fetchXml(string $url): SimpleXMLElement
    {
        $body = Http::withoutVerifying()
            ->accept('application/rss+xml, application/xml, text/xml')
            ->timeout(15)
            ->get($url)
            ->throw()
            ->body();

        return $this->parseXml($body);
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

    private function buildSummary(string $html): ?string
    {
        $text = strip_tags($html);
        $text = $this->normalizeText($text);
        if ($text === null) {
            return null;
        }

        $max = 320;
        $length = function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);
        if ($length > $max) {
            $excerpt = function_exists('mb_substr')
                ? mb_substr($text, 0, $max)
                : substr($text, 0, $max);
            // Try to end at word boundary
            $lastSpace = strrpos($excerpt, ' ');
            if ($lastSpace !== false && $lastSpace > $max * 0.8) {
                $excerpt = substr($excerpt, 0, $lastSpace);
            }
            return $excerpt . '…';
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
}
