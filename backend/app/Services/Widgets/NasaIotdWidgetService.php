<?php

namespace App\Services\Widgets;

use App\Services\Translation\TranslationServiceException;
use App\Services\TranslationService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use SimpleXMLElement;

class NasaIotdWidgetService
{
    public const FEED_URL = 'https://www.nasa.gov/feeds/iotd-feed/';
    public const APOD_API_URL = 'https://api.nasa.gov/planetary/apod';

    public function __construct(
        private readonly TranslationService $translationService,
    ) {
    }

    /**
     * @return array<string,mixed>
     */
    public function payload(): array
    {
        $cacheKey = 'nasa_iotd_widget_v3';
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $payload = $this->buildPayload();
        $ttl = !empty($payload['available']) ? now()->addHour() : now()->addMinutes(5);
        Cache::put($cacheKey, $payload, $ttl);

        return $payload;
    }

    /**
     * @return array<string,mixed>
     */
    private function buildPayload(): array
    {
        $fetchedAt = CarbonImmutable::now('UTC')->toIso8601String();

        try {
            $xml = $this->fetchFeed();
            $item = $this->extractLatestItem($xml);
            if ($item) {
                $rssPayload = $this->decoratePayload(
                    $this->mapRssItem($item),
                    [
                        'provider' => 'nasa',
                        'label' => 'NASA IOTD RSS',
                        'url' => self::FEED_URL,
                    ],
                    $fetchedAt,
                );
                if (!empty($rssPayload['available'])) {
                    return $rssPayload;
                }
            }
        } catch (\Throwable) {
            // fallback below
        }

        try {
            return $this->decoratePayload(
                $this->fetchApodApiPayload(),
                [
                    'provider' => 'nasa',
                    'label' => 'NASA APOD',
                    'url' => self::APOD_API_URL,
                ],
                $fetchedAt,
            );
        } catch (\Throwable) {
            return $this->decoratePayload(
                ['available' => false],
                [
                    'provider' => 'nasa',
                    'label' => 'NASA',
                    'url' => self::FEED_URL,
                ],
                $fetchedAt,
            );
        }
    }

    /**
     * @param  array<string,mixed>  $payload
     * @param  array<string,string>  $source
     * @return array<string,mixed>
     */
    private function decoratePayload(array $payload, array $source, string $fetchedAt): array
    {
        return [
            ...$payload,
            'source' => $source,
            'updated_at' => $fetchedAt,
        ];
    }

    private function fetchFeed(): SimpleXMLElement
    {
        $body = $this->requestWithSslFallback(
            fn (PendingRequest $request) => $request
                ->accept('application/rss+xml, application/xml, text/xml')
                ->get(self::FEED_URL)
        )->throw()->body();

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

        if (! $items) {
            return null;
        }

        $first = $items[0] ?? null;
        return $first instanceof SimpleXMLElement ? $first : null;
    }

    private function extractContentEncoded(SimpleXMLElement $item): string
    {
        try {
            $content = $item->children('content', true);
            return (string) ($content->encoded ?? '');
        } catch (\Throwable) {
            return '';
        }
    }

    private function extractImageUrl(SimpleXMLElement $item, string $contentEncoded, string $description): ?string
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
            $url = trim($matches[1] ?? '');
            if ($url !== '') {
                return $url;
            }
        }

        return null;
    }

    /**
     * @return array<string,mixed>
     */
    private function mapRssItem(SimpleXMLElement $item): array
    {
        $title = $this->normalizeText((string) ($item->title ?? ''));
        $link = trim((string) ($item->link ?? ''));

        $description = (string) ($item->description ?? '');
        $contentEncoded = $this->extractContentEncoded($item);

        $imageUrl = $this->extractImageUrl($item, $contentEncoded, $description);
        if (! $imageUrl || ! $title || ! $link) {
            return ['available' => false];
        }

        $excerpt = $this->buildExcerpt($contentEncoded !== '' ? $contentEncoded : $description);
        $title = $this->translateWidgetText($title);
        $excerpt = $this->translateWidgetText($excerpt);

        return [
            'available' => true,
            'title' => $title,
            'excerpt' => $excerpt,
            'image_url' => $imageUrl,
            'link' => $link,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function fetchApodApiPayload(): array
    {
        $apiKey = (string) config('services.nasa.apod_api_key', 'DEMO_KEY');

        $json = $this->requestWithSslFallback(
            fn (PendingRequest $request) => $request
                ->acceptJson()
                ->get(self::APOD_API_URL, [
                    'api_key' => $apiKey,
                ])
        )->throw()->json();

        $title = $this->normalizeText((string) ($json['title'] ?? ''));
        $link = trim((string) ($json['hdurl'] ?? $json['url'] ?? ''));
        $mediaType = strtolower(trim((string) ($json['media_type'] ?? '')));
        $imageUrl = trim((string) ($json['url'] ?? $json['hdurl'] ?? ''));

        if ($mediaType === 'video') {
            $imageUrl = trim((string) ($json['thumbnail_url'] ?? ''));
        }

        if ($title === null || $link === '' || $imageUrl === '') {
            return ['available' => false];
        }

        $excerpt = $this->buildExcerpt((string) ($json['explanation'] ?? ''));
        $title = $this->translateWidgetText($title);
        $excerpt = $this->translateWidgetText($excerpt);

        return [
            'available' => true,
            'title' => $title,
            'excerpt' => $excerpt,
            'image_url' => $imageUrl,
            'link' => $link,
        ];
    }

    private function requestWithSslFallback(callable $requestBuilder)
    {
        try {
            $primary = $requestBuilder(Http::secure()->timeout(15));
            if ($primary->successful()) {
                return $primary;
            }
        } catch (\Throwable) {
            // retry below with disabled SSL verification
        }

        return $requestBuilder(
            Http::withOptions(['verify' => false])->timeout(15)
        );
    }

    private function buildExcerpt(string $html): ?string
    {
        $text = strip_tags($html);
        $text = $this->normalizeText($text);
        if ($text === null) {
            return null;
        }

        $maxLength = 240;
        $length = function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);
        if ($length > $maxLength) {
            return function_exists('mb_substr')
                ? mb_substr($text, 0, $maxLength)
                : substr($text, 0, $maxLength);
        }

        return $text;
    }

    private function normalizeText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim($value);
        if ($normalized === '') {
            return null;
        }

        $normalized = str_replace(['&amp;#', '&amp;nbsp;'], ['&#', ' '], $normalized);
        $normalized = preg_replace('/&#(\d+)(?!;)/', '&#$1;', $normalized) ?? $normalized;
        $normalized = preg_replace('/&#x([0-9a-fA-F]+)(?!;)/', '&#x$1;', $normalized) ?? $normalized;

        for ($index = 0; $index < 2; $index++) {
            $decoded = html_entity_decode($normalized, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($decoded === $normalized) {
                break;
            }

            $normalized = $decoded;
        }

        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;
        $normalized = trim($normalized);

        return $normalized !== '' ? $normalized : null;
    }

    private function translateWidgetText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        try {
            $translated = $this->translationService->translateEnToSk($value, 'astronomy');
            return $this->normalizeText($translated) ?? $value;
        } catch (TranslationServiceException $exception) {
            Log::warning('NASA widget translation failed via translation service.', [
                'error_code' => $exception->errorCode(),
                'status_code' => $exception->statusCode(),
                'message' => $exception->getMessage(),
            ]);

            return $value;
        } catch (\Throwable $exception) {
            Log::warning('NASA widget translation failed unexpectedly.', [
                'message' => $exception->getMessage(),
            ]);

            return $value;
        }
    }
}
