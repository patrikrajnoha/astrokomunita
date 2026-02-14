<?php

namespace App\Services;

use App\Jobs\TranslateRssItemJob;
use App\Models\RssItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use SimpleXMLElement;

class AstroBotRssService
{
    /**
     * @return array{
     *   added:int,
     *   updated:int,
     *   deleted:int,
     *   skipped:int,
     *   errors:int,
     *   synced_at:string,
     *   evaluate_item_ids:array<int,int>
     * }
     */
    public function sync(): array
    {
        $rssUrl = (string) config('astrobot.rss_url', RssFetchService::NASA_NEWS_FEED_URL);
        $maxItems = max(1, (int) config('astrobot.max_items_per_sync', config('astrobot.rss_max_items', 80)));
        $maxAgeDays = max(1, (int) config('astrobot.max_age_days', 30));
        $timeout = max(1, (int) config('astrobot.rss_timeout_seconds', 10));
        $retryTimes = max(0, (int) config('astrobot.rss_retry_times', 2));
        $retrySleepMs = max(0, (int) config('astrobot.rss_retry_sleep_ms', 300));
        $maxPayloadBytes = max(1024, (int) config('astrobot.rss_max_payload_kb', 1024) * 1024);
        $source = RssFetchService::SOURCE_NASA_NEWS;

        $stats = [
            'added' => 0,
            'updated' => 0,
            'deleted' => 0,
            'skipped' => 0,
            'errors' => 0,
            'synced_at' => now()->toIso8601String(),
            'evaluate_item_ids' => [],
        ];

        Log::info('AstroBot RSS sync started', [
            'url' => $rssUrl,
            'max_items_per_sync' => $maxItems,
        ]);

        try {
            $response = Http::accept('application/rss+xml, application/xml, text/xml, application/atom+xml')
                ->withUserAgent((string) config('astrobot.rss_user_agent', 'AstroKomunita AstroBot RSS Sync'))
                ->timeout($timeout)
                ->retry($retryTimes, $retrySleepMs, throw: false)
                ->get($rssUrl);
        } catch (\Throwable $e) {
            $stats['errors']++;
            Log::warning('AstroBot RSS sync fetch failed', [
                'url' => $rssUrl,
                'exception' => $e->getMessage(),
            ]);
            Log::info('AstroBot RSS sync finished', $stats);
            return $stats;
        }

        if (! $response->successful()) {
            $stats['errors']++;
            Log::warning('AstroBot RSS sync HTTP error', [
                'url' => $rssUrl,
                'status' => $response->status(),
            ]);
            Log::info('AstroBot RSS sync finished', $stats);
            return $stats;
        }

        $payload = (string) $response->body();
        if (strlen($payload) > $maxPayloadBytes) {
            $stats['errors']++;
            Log::warning('AstroBot RSS sync payload too large', [
                'url' => $rssUrl,
                'payload_bytes' => strlen($payload),
                'max_payload_bytes' => $maxPayloadBytes,
            ]);
            Log::info('AstroBot RSS sync finished', $stats);
            return $stats;
        }

        $xml = $this->parseXmlSafely($payload, $rssUrl);
        if ($xml === null) {
            $stats['errors']++;
            Log::info('AstroBot RSS sync finished', $stats);
            return $stats;
        }

        $rows = $this->extractItems($xml);
        if ($rows === []) {
            Log::warning('AstroBot RSS sync found empty feed', ['url' => $rssUrl]);
            Log::info('AstroBot RSS sync finished', $stats);
            return $stats;
        }

        usort($rows, static function (array $a, array $b): int {
            $aDate = $a['published_at']?->timestamp ?? 0;
            $bDate = $b['published_at']?->timestamp ?? 0;
            return $bDate <=> $aDate;
        });

        $allowMissingCleanup = count($rows) <= $maxItems;
        $rows = array_slice($rows, 0, $maxItems);

        $processedStableKeys = [];
        $seenGuids = [];

        foreach ($rows as $row) {
            $title = $this->normalizeText($row['title'] ?? '');
            $link = trim((string) ($row['link'] ?? ''));
            $guid = trim((string) ($row['guid'] ?? ''));
            $publishedAt = $row['published_at'] instanceof Carbon ? $row['published_at'] : null;
            $summary = $this->normalizeText($row['summary'] ?? '');

            if ($title === '' || $link === '') {
                $stats['skipped']++;
                continue;
            }

            $stableKey = $this->buildStableKey($guid, $link, $publishedAt, $seenGuids);
            if (isset($processedStableKeys[$stableKey])) {
                $stats['skipped']++;
                continue;
            }
            $processedStableKeys[$stableKey] = true;

            $payloadForUpsert = [
                'source' => $source,
                'guid' => $guid !== '' ? $guid : null,
                'url' => $link,
                'title' => Str::limit($title, 255),
                'original_title' => Str::limit($title, 255),
                'summary' => $summary !== '' ? Str::limit($summary, 2000) : null,
                'original_summary' => $summary !== '' ? Str::limit($summary, 2000) : null,
                'published_at' => $publishedAt,
                'fetched_at' => now(),
                'dedupe_hash' => $stableKey,
                'stable_key' => $stableKey,
            ];

            $existing = RssItem::query()->where('stable_key', $stableKey)->first();
            $isNew = $existing === null;

            if ($isNew) {
                $payloadForUpsert['status'] = RssItem::STATUS_DRAFT;
                $payloadForUpsert['translation_status'] = RssItem::TRANSLATION_PENDING;
                $payloadForUpsert['translation_error'] = null;
            }

            $item = RssItem::query()->updateOrCreate(
                ['stable_key' => $stableKey],
                $payloadForUpsert
            );

            if ($isNew) {
                $stats['added']++;
                $stats['evaluate_item_ids'][] = $item->id;
                TranslateRssItemJob::dispatch($item->id)->afterCommit();
                continue;
            }

            if ($existing !== null && $this->hasImportChanges($existing, $payloadForUpsert)) {
                $item->update([
                    'translated_title' => null,
                    'translated_summary' => null,
                    'translation_status' => RssItem::TRANSLATION_PENDING,
                    'translation_error' => null,
                    'translated_at' => null,
                ]);
                $stats['updated']++;
                $stats['evaluate_item_ids'][] = $item->id;
                TranslateRssItemJob::dispatch($item->id)->afterCommit();
            } else {
                $stats['skipped']++;
            }
        }

        if ($allowMissingCleanup && $processedStableKeys !== []) {
            $stats['deleted'] += RssItem::query()
                ->where('source', $source)
                ->whereNotIn('stable_key', array_keys($processedStableKeys))
                ->delete();
        }

        $stats['deleted'] += RssItem::query()
            ->where('created_at', '<', now()->subDays($maxAgeDays))
            ->delete();

        Log::info('AstroBot RSS sync finished', [
            'added' => $stats['added'],
            'updated' => $stats['updated'],
            'deleted' => $stats['deleted'],
            'skipped' => $stats['skipped'],
            'errors' => $stats['errors'],
        ]);

        return $stats;
    }

    private function hasImportChanges(RssItem $existing, array $payload): bool
    {
        return $existing->title !== (string) ($payload['title'] ?? '')
            || $existing->url !== (string) ($payload['url'] ?? '')
            || ($existing->summary ?? null) !== ($payload['summary'] ?? null)
            || (optional($existing->published_at)->toIso8601String() !== optional($payload['published_at'])->toIso8601String());
    }

    private function parseXmlSafely(string $payload, string $rssUrl): ?SimpleXMLElement
    {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($payload, SimpleXMLElement::class, LIBXML_NOCDATA | LIBXML_NONET);
        if ($xml !== false) {
            libxml_clear_errors();
            return $xml;
        }

        $errors = libxml_get_errors();
        libxml_clear_errors();
        $firstError = $errors[0]->message ?? 'Invalid XML payload.';

        Log::warning('AstroBot RSS sync parse failed', [
            'url' => $rssUrl,
            'error' => trim($firstError),
        ]);

        return null;
    }

    /**
     * @return array<int,array{guid:string,link:string,title:string,published_at:?Carbon,summary:string}>
     */
    private function extractItems(SimpleXMLElement $xml): array
    {
        $rows = [];

        if (isset($xml->channel->item)) {
            foreach ($xml->channel->item as $item) {
                $rows[] = [
                    'guid' => trim((string) ($item->guid ?? '')),
                    'link' => trim((string) ($item->link ?? '')),
                    'title' => trim((string) ($item->title ?? '')),
                    'published_at' => $this->parseDate((string) ($item->pubDate ?? '')),
                    'summary' => (string) ($item->description ?? ''),
                ];
            }

            return $rows;
        }

        if (isset($xml->entry)) {
            foreach ($xml->entry as $entry) {
                $link = '';
                if (isset($entry->link)) {
                    $attributes = $entry->link->attributes();
                    $link = trim((string) ($attributes['href'] ?? (string) $entry->link));
                }

                $rows[] = [
                    'guid' => trim((string) ($entry->id ?? '')),
                    'link' => $link,
                    'title' => trim((string) ($entry->title ?? '')),
                    'published_at' => $this->parseDate((string) ($entry->updated ?? $entry->published ?? '')),
                    'summary' => (string) ($entry->summary ?? $entry->content ?? ''),
                ];
            }
        }

        return $rows;
    }

    /**
     * @param array<string,int> $seenGuids
     */
    private function buildStableKey(string $guid, string $link, ?Carbon $publishedAt, array &$seenGuids): string
    {
        if ($guid !== '') {
            $seenGuids[$guid] = ($seenGuids[$guid] ?? 0) + 1;
            if ($seenGuids[$guid] === 1) {
                return sha1('guid:' . $guid);
            }

            Log::warning('AstroBot RSS sync duplicate GUID in feed, fallback stable key used', [
                'guid' => $guid,
                'link' => $link,
            ]);
        }

        $publishedKey = $publishedAt?->toIso8601String() ?? 'no-date';
        return sha1('fallback:' . strtolower($link) . '|' . $publishedKey);
    }

    private function parseDate(string $value): ?Carbon
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizeText(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        $value = strip_tags($value);
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }
}
