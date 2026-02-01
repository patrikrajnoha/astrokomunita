<?php

namespace App\Services;

use App\Models\Post;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use SimpleXMLElement;

class NasaRssImportService
{
    public const SOURCE_NAME = 'nasa_rss';
    public const FEED_URL = 'https://www.nasa.gov/news-release/feed/';

    /**
     * @return array{total:int,inserted:int,duplicates:int,errors:int}
     */
    public function import(int $limit = 20): array
    {
        $limit = $limit > 0 ? $limit : 20;

        $xml = $this->fetchFeed();
        $items = $this->extractItems($xml);

        $available = is_countable($items) ? count($items) : 0;
        $total = $limit > 0 ? min($available, $limit) : $available;

        $inserted = 0;
        $duplicates = 0;
        $errors = 0;

        $astroBot = $this->ensureAstroBotUser();
        $processed = 0;

        foreach ($items as $item) {
            if ($limit > 0 && $processed >= $limit) {
                break;
            }
            $processed++;

            $title = $this->normalizeText((string) ($item->title ?? ''));
            if ($title === null) {
                $errors++;
                continue;
            }

            $link = trim((string) ($item->link ?? ''));
            if ($link === '') {
                $errors++;
                continue;
            }

            $guid = trim((string) ($item->guid ?? ''));
            $description = (string) ($item->description ?? '');
            $pubDate = (string) ($item->pubDate ?? '');

            $excerpt = $this->buildExcerpt($description);
            $sourceUid = $guid !== '' ? $guid : sha1($link);
            $sourcePublishedAt = $this->parsePubDate($pubDate);

            $content = $this->buildContent($title, $excerpt, $link);

            $post = Post::query()->firstOrCreate(
                [
                    'source_name' => self::SOURCE_NAME,
                    'source_uid' => $sourceUid,
                ],
                [
                    'user_id' => $astroBot->id,
                    'content' => $content,
                    'source_name' => self::SOURCE_NAME,
                    'source_url' => $link,
                    'source_uid' => $sourceUid,
                    'source_published_at' => $sourcePublishedAt,
                ]
            );

            if ($post->wasRecentlyCreated) {
                $inserted++;
            } else {
                $duplicates++;
            }
        }

        return [
            'total' => $total,
            'inserted' => $inserted,
            'duplicates' => $duplicates,
            'errors' => $errors,
        ];
    }

    private function fetchFeed(): SimpleXMLElement
    {
        $payload = Http::withoutVerifying()
            ->accept('application/rss+xml, application/xml, text/xml')
            ->get(self::FEED_URL)
            ->throw()
            ->body();

        return $this->parseXml($payload);
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

    private function buildExcerpt(string $description): ?string
    {
        $text = strip_tags($description);
        $text = $this->normalizeText($text);
        if ($text === null) {
            return null;
        }

        $max = 240;
        $length = function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);
        if ($length > $max) {
            return function_exists('mb_substr')
                ? mb_substr($text, 0, $max)
                : substr($text, 0, $max);
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

    private function buildContent(string $title, ?string $excerpt, string $url): string
    {
        $lines = [
            sprintf('🚀 NASA: %s', $title),
        ];

        if ($excerpt) {
            $lines[] = $excerpt;
        }

        $lines[] = $url;

        return implode("\n", $lines);
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

    private function ensureAstroBotUser(): User
    {
        return User::query()->firstOrCreate(
            ['email' => 'astrobot@astrokomunita.local'],
            [
                'name' => 'AstroBot',
                'bio' => 'Automated space news from NASA RSS',
                'password' => Str::random(40),
            ]
        );
    }
}
