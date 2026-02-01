<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use SimpleXMLElement;

class NasaIotdController extends Controller
{
    public const FEED_URL = 'https://www.nasa.gov/feeds/iotd-feed/';

    public function show(): JsonResponse
    {
        $payload = Cache::remember('nasa_iotd_widget_v1', now()->addHour(), function () {
            try {
                $xml = $this->fetchFeed();
                $item = $this->extractLatestItem($xml);

                if (!$item) {
                    return ['available' => false];
                }

                $title = $this->normalizeText((string) ($item->title ?? ''));
                $link = trim((string) ($item->link ?? ''));

                $description = (string) ($item->description ?? '');
                $contentEncoded = $this->extractContentEncoded($item);

                $imageUrl = $this->extractImageUrl($item, $contentEncoded, $description);
                if (!$imageUrl || !$title || !$link) {
                    return ['available' => false];
                }

                $excerpt = $this->buildExcerpt($contentEncoded !== '' ? $contentEncoded : $description);

                return [
                    'available' => true,
                    'title' => $title,
                    'excerpt' => $excerpt,
                    'image_url' => $imageUrl,
                    'link' => $link,
                ];
            } catch (\Throwable) {
                return ['available' => false];
            }
        });

        return response()->json($payload);
    }

    private function fetchFeed(): SimpleXMLElement
    {
        $body = Http::withoutVerifying()
            ->accept('application/rss+xml, application/xml, text/xml')
            ->get(self::FEED_URL)
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

    private function extractLatestItem(SimpleXMLElement $xml): ?SimpleXMLElement
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

    private function extractContentEncoded(SimpleXMLElement $item): string
    {
        try {
            $content = $item->children('content', true);
            $encoded = (string) ($content->encoded ?? '');
            return $encoded;
        } catch (\Throwable) {
            return '';
        }
    }

    private function extractImageUrl(SimpleXMLElement $item, string $contentEncoded, string $description): ?string
    {
        // 1) media:content / media:thumbnail
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

        // 2) enclosure
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

        // 3) <img src="..."> in content/description
        $html = $contentEncoded !== '' ? $contentEncoded : $description;
        if ($html !== '') {
            if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $html, $m)) {
                $url = trim($m[1] ?? '');
                if ($url !== '') {
                    return $url;
                }
            }
        }

        return null;
    }

    private function buildExcerpt(string $html): ?string
    {
        $text = strip_tags($html);
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
