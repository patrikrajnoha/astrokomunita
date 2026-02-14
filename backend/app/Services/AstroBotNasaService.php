<?php

namespace App\Services;

use App\Exceptions\AstroBotSyncInProgressException;
use App\Jobs\TranslateRssItemJob;
use App\Models\AstroBotRun;
use App\Models\Post;
use App\Models\RssItem;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use SimpleXMLElement;

class AstroBotNasaService
{
    public const SOURCE = 'nasa';
    public const LOCK_KEY = 'astrobot:nasa:sync:lock';

    /**
     * @return array{
     *   new:int,
     *   published:int,
     *   deleted:int,
     *   duration_ms:int,
     *   errors:int,
     *   started_at:string,
     *   finished_at:string,
     *   run_id:int|null,
     *   error:?string
     * }
     */
    public function syncWithLock(string $trigger = 'scheduler'): array
    {
        $lockTtlSeconds = max(60, (int) config('astrobot.lock_ttl_seconds', 3300));
        $lock = Cache::lock(self::LOCK_KEY, $lockTtlSeconds);

        if (! $lock->get()) {
            throw new AstroBotSyncInProgressException('NASA RSS sync is already running.');
        }

        try {
            return $this->runSync($trigger);
        } finally {
            $lock->release();
        }
    }

    /**
     * @return array{
     *   new:int,
     *   published:int,
     *   deleted:int,
     *   duration_ms:int,
     *   errors:int,
     *   started_at:string,
     *   finished_at:string,
     *   run_id:int|null,
     *   error:?string
     * }
     */
    public function runSync(string $trigger = 'scheduler'): array
    {
        $startedAt = now();
        $startMicrotime = microtime(true);

        $summary = [
            'new' => 0,
            'published' => 0,
            'deleted' => 0,
            'duration_ms' => 0,
            'errors' => 0,
            'started_at' => $startedAt->toIso8601String(),
            'finished_at' => $startedAt->toIso8601String(),
            'run_id' => null,
            'error' => null,
        ];

        try {
            if (! (bool) config('astrobot.enabled', true)) {
                $finishedAt = now();
                $summary['finished_at'] = $finishedAt->toIso8601String();
                $summary['duration_ms'] = (int) round((microtime(true) - $startMicrotime) * 1000);
                $summary['run_id'] = $this->storeRun($summary, $trigger, 'skipped');
                return $summary;
            }

            $feedItems = $this->fetchFeed();
            $summary['new'] = $this->upsertItems($feedItems);
            $summary['published'] = $this->publishNew();
            $summary['deleted'] = $this->pruneOld();
        } catch (\Throwable $e) {
            $summary['errors'] = 1;
            $summary['error'] = $e->getMessage();
            Log::warning('AstroBot NASA sync failed', ['error' => $e->getMessage()]);
        }

        $summary['finished_at'] = now()->toIso8601String();
        $summary['duration_ms'] = (int) round((microtime(true) - $startMicrotime) * 1000);
        $summary['run_id'] = $this->storeRun(
            $summary,
            $trigger,
            $summary['errors'] > 0 ? 'error' : 'success'
        );

        Log::info('AstroBot NASA sync finished', [
            'new' => $summary['new'],
            'published' => $summary['published'],
            'deleted' => $summary['deleted'],
            'duration_ms' => $summary['duration_ms'],
            'errors' => $summary['errors'],
        ]);

        return $summary;
    }

    /**
     * @return array<int,array{guid:?string,link:string,title:string,summary:?string,published_at:?Carbon,fingerprint:string}>
     */
    public function fetchFeed(): array
    {
        $url = (string) config('astrobot.nasa_rss_url');
        $timeout = max(1, (int) config('astrobot.rss_timeout_seconds', 12));
        $retryTimes = max(0, (int) config('astrobot.rss_retry_times', 2));
        $retrySleepMs = max(0, (int) config('astrobot.rss_retry_sleep_ms', 250));
        $maxItems = max(1, (int) config('astrobot.max_items_per_sync', 100));
        $verifyOption = $this->resolveVerifyOption();

        try {
            $response = Http::withOptions(['verify' => $verifyOption])
                ->accept('application/rss+xml, application/xml, text/xml, application/atom+xml')
                ->withUserAgent((string) config('astrobot.rss_user_agent', 'AstroKomunita/AstroBot NASA Sync'))
                ->timeout($timeout)
                ->retry($retryTimes, $retrySleepMs, throw: false)
                ->get($url);
        } catch (ConnectionException $e) {
            throw new \RuntimeException($this->buildFetchErrorMessage($e->getMessage(), $verifyOption), previous: $e);
        }

        if (! $response->successful()) {
            throw new \RuntimeException(sprintf(
                'NASA RSS HTTP error: %d (url: %s)',
                $response->status(),
                $url
            ));
        }

        $xml = $this->parseXml((string) $response->body());
        $items = $this->extractItems($xml);

        usort($items, static fn (array $a, array $b): int => ($b['published_at']?->timestamp ?? 0) <=> ($a['published_at']?->timestamp ?? 0));

        return array_slice($items, 0, $maxItems);
    }

    private function resolveVerifyOption(): bool|string
    {
        $configuredBundle = trim((string) config('astrobot.ssl_ca_bundle', ''));
        $configuredVerify = (bool) config('astrobot.ssl_verify', true);

        $candidates = array_filter([
            $configuredBundle !== '' ? $configuredBundle : null,
            storage_path('cacert.pem'),
            base_path('cacert.pem'),
            storage_path('certs/cacert.pem'),
        ]);

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && is_file($candidate)) {
                return $candidate;
            }
        }

        return $configuredVerify;
    }

    private function buildFetchErrorMessage(string $message, bool|string $verifyOption): string
    {
        $normalized = trim($message);
        $isSslError = str_contains(strtolower($normalized), 'ssl')
            || str_contains(strtolower($normalized), 'certificate')
            || str_contains(strtolower($normalized), 'curl error 60');

        if (! $isSslError) {
            return $normalized;
        }

        $verifyHint = is_string($verifyOption)
            ? 'CA bundle: ' . $verifyOption
            : 'verify=' . ($verifyOption ? 'true' : 'false');

        return sprintf(
            'NASA RSS SSL verify failed: %s. %s. Configure ASTROBOT_SSL_CA_BUNDLE with a valid cacert.pem path.',
            $normalized,
            $verifyHint
        );
    }

    /**
     * @param array<int,array{guid:?string,link:string,title:string,summary:?string,published_at:?Carbon,fingerprint:string}> $feedItems
     */
    public function upsertItems(array $feedItems): int
    {
        $created = 0;

        foreach ($feedItems as $row) {
            $existing = RssItem::query()
                ->where('stable_key', $row['fingerprint'])
                ->first();

            $payload = [
                'source' => self::SOURCE,
                'guid' => $row['guid'],
                'url' => $row['link'],
                'title' => Str::limit($row['title'], 255),
                'summary' => $row['summary'] ? Str::limit($row['summary'], 2000) : null,
                'original_title' => Str::limit($row['title'], 255),
                'original_summary' => $row['summary'] ? Str::limit($row['summary'], 2000) : null,
                'published_at' => $row['published_at'],
                'fetched_at' => now(),
                'stable_key' => $row['fingerprint'],
                'dedupe_hash' => $row['fingerprint'],
            ];

            if (! $existing) {
                $createdItem = RssItem::query()->create($payload + [
                    'status' => RssItem::STATUS_DRAFT,
                    'translation_status' => RssItem::TRANSLATION_PENDING,
                    'translation_error' => null,
                ]);
                TranslateRssItemJob::dispatch($createdItem->id)->afterCommit();
                $created++;
                continue;
            }

            $textChanged = $existing->title !== $payload['title']
                || (string) ($existing->summary ?? '') !== (string) ($payload['summary'] ?? '');

            if ($existing->status !== RssItem::STATUS_PUBLISHED || ! $existing->post_id) {
                $payload['status'] = RssItem::STATUS_DRAFT;
                $payload['last_error'] = null;
            }

            if ($textChanged) {
                $payload['translated_title'] = null;
                $payload['translated_summary'] = null;
                $payload['translation_status'] = RssItem::TRANSLATION_PENDING;
                $payload['translation_error'] = null;
                $payload['translated_at'] = null;
            }

            $existing->fill($payload)->save();

            if ($textChanged || $existing->translation_status !== RssItem::TRANSLATION_DONE) {
                TranslateRssItemJob::dispatch($existing->id)->afterCommit();
            }
        }

        return $created;
    }

    public function publishNew(): int
    {
        if (! (bool) config('astrobot.auto_publish_enabled', true)) {
            RssItem::query()
                ->where('source', self::SOURCE)
                ->where('translation_status', RssItem::TRANSLATION_DONE)
                ->whereIn('status', [
                    RssItem::STATUS_DRAFT,
                    RssItem::STATUS_PENDING,
                    RssItem::STATUS_APPROVED,
                    RssItem::STATUS_ERROR,
                ])
                ->update([
                    'status' => RssItem::STATUS_NEEDS_REVIEW,
                ]);

            return 0;
        }

        $items = RssItem::query()
            ->where('source', self::SOURCE)
            ->whereIn('status', [
                RssItem::STATUS_DRAFT,
                RssItem::STATUS_PENDING,
                RssItem::STATUS_APPROVED,
                RssItem::STATUS_NEEDS_REVIEW,
                RssItem::STATUS_ERROR,
            ])
            ->where('translation_status', RssItem::TRANSLATION_DONE)
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->get();

        $published = 0;

        foreach ($items as $item) {
            try {
                $this->publisher->publish($item);
                $published++;
            } catch (\Throwable $e) {
                $item->update([
                    'status' => RssItem::STATUS_ERROR,
                    'last_error' => $e->getMessage(),
                ]);
            }
        }

        return $published;
    }

    public function pruneOld(): int
    {
        $deleted = 0;
        $keepMaxDays = max(1, (int) config('astrobot.keep_max_days', 14));
        $keepMaxItems = max(1, (int) config('astrobot.keep_max_items', 30));

        $baseQuery = Post::query()
            ->select('posts.id')
            ->join('rss_items', 'rss_items.post_id', '=', 'posts.id')
            ->where('rss_items.source', self::SOURCE)
            ->whereIn('posts.source_name', ['astrobot', 'nasa_rss']);

        $ageCutoff = now()->subDays($keepMaxDays);
        $ageIds = (clone $baseQuery)
            ->whereRaw('COALESCE(posts.source_published_at, posts.created_at) < ?', [$ageCutoff])
            ->pluck('posts.id')
            ->unique()
            ->values();

        if ($ageIds->isNotEmpty()) {
            $deleted += Post::query()->whereIn('id', $ageIds)->delete();
        }

        $remainingIds = (clone $baseQuery)
            ->orderByRaw('COALESCE(posts.source_published_at, posts.created_at) DESC')
            ->orderByDesc('posts.id')
            ->pluck('posts.id')
            ->unique()
            ->values();

        if ($remainingIds->count() > $keepMaxItems) {
            $overflowIds = $remainingIds->slice($keepMaxItems)->values();
            if ($overflowIds->isNotEmpty()) {
                $deleted += Post::query()->whereIn('id', $overflowIds)->delete();
            }
        }

        return $deleted;
    }

    public function latestRun(): ?AstroBotRun
    {
        return AstroBotRun::query()
            ->where('source', self::SOURCE)
            ->latest('id')
            ->first();
    }

    private function storeRun(array $summary, string $trigger, string $status): ?int
    {
        $run = AstroBotRun::query()->create([
            'source' => self::SOURCE,
            'trigger' => $trigger,
            'status' => $status,
            'started_at' => Carbon::parse($summary['started_at']),
            'finished_at' => Carbon::parse($summary['finished_at']),
            'duration_ms' => (int) $summary['duration_ms'],
            'new_items' => (int) $summary['new'],
            'published_items' => (int) $summary['published'],
            'deleted_items' => (int) $summary['deleted'],
            'errors' => (int) $summary['errors'],
            'error_message' => $summary['error'],
            'meta' => [
                'keep_max_items' => (int) config('astrobot.keep_max_items', 30),
                'keep_max_days' => (int) config('astrobot.keep_max_days', 14),
            ],
        ]);

        return $run->id;
    }

    private function parseXml(string $payload): SimpleXMLElement
    {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($payload, SimpleXMLElement::class, LIBXML_NOCDATA | LIBXML_NONET);

        if ($xml !== false) {
            libxml_clear_errors();
            return $xml;
        }

        $errors = libxml_get_errors();
        libxml_clear_errors();
        $message = trim($errors[0]->message ?? 'Invalid NASA RSS payload.');

        throw new \RuntimeException($message);
    }

    /**
     * @return array<int,array{guid:?string,link:string,title:string,summary:?string,published_at:?Carbon,fingerprint:string}>
     */
    private function extractItems(SimpleXMLElement $xml): array
    {
        $rows = [];

        if (isset($xml->channel->item)) {
            foreach ($xml->channel->item as $item) {
                $guid = $this->normalizeText((string) ($item->guid ?? ''));
                $link = $this->normalizeText((string) ($item->link ?? ''));
                $title = $this->normalizeText((string) ($item->title ?? ''));

                if ($link === '' || $title === '') {
                    continue;
                }

                $publishedAt = $this->parseDate((string) ($item->pubDate ?? ''));
                $summary = $this->normalizeText((string) ($item->description ?? ''));
                $fingerprint = $this->fingerprint($guid, $link, $title, $publishedAt);

                $rows[] = [
                    'guid' => $guid !== '' ? $guid : null,
                    'link' => $link,
                    'title' => $title,
                    'summary' => $summary !== '' ? $summary : null,
                    'published_at' => $publishedAt,
                    'fingerprint' => $fingerprint,
                ];
            }
        }

        return $rows;
    }

    private function fingerprint(?string $guid, string $link, string $title, ?Carbon $publishedAt): string
    {
        $normalizedGuid = trim((string) $guid);
        if ($normalizedGuid !== '') {
            return sha1('guid:' . $normalizedGuid);
        }

        $normalizedLink = strtolower(trim($link));
        if ($normalizedLink !== '') {
            return sha1('link:' . $normalizedLink);
        }

        $published = $publishedAt?->toIso8601String() ?? 'no-date';
        return sha1('fallback:' . mb_strtolower(trim($title)) . '|' . $published . '|' . $normalizedLink);
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

    private function normalizeText(string $value): string
    {
        $value = strip_tags($value);
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        return trim($value);
    }

    public function __construct(
        private readonly AstroBotPublisher $publisher,
    ) {
    }
}
