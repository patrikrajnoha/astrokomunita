<?php

namespace App\Services\Bots;

use App\Enums\BotPublishStatus;
use App\Models\BotSource;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class WikipediaOnThisDayFetchService
{
    private const SOURCE_TAG = 'wikipedia_onthisday';
    private const MAX_SUBCLASS_DEPTH = 4;

    /** @var array<int,string> */
    private const KEYWORDS = [
        'astronomy', 'space', 'planet', 'comet', 'asteroid', 'telescope', 'satellite', 'nasa', 'probe',
        'mission', 'orbit', 'galaxy', 'supernova', 'moon', 'mars', 'jupiter', 'venus', 'mercury',
        'uranus', 'neptune', 'pluto', 'eclipse', 'cosmic', 'observatory', 'apollo', 'iss', 'spacecraft',
        'rocket', 'lunar', 'solar', 'star',
    ];

    /** @var array<int,string> */
    private const DEFAULT_ALLOWLIST_QIDS = [
        'Q6999', 'Q1153690', 'Q634', 'Q2537', 'Q3863', 'Q3559', 'Q318', 'Q523', 'Q9008', 'Q130427',
        'Q2133344', 'Q40218', 'Q26529', 'Q26540', 'Q190107', 'Q8354', 'Q11631', 'Q11063', 'Q699182',
    ];

    /** @var array<int,string> */
    private const DEFAULT_DENYLIST_QIDS = [
        'Q5', 'Q11424', 'Q5398426', 'Q7366', 'Q482994', 'Q7889', 'Q571', 'Q8261', 'Q783794', 'Q43229',
        'Q12973014', 'Q82955',
    ];

    /** @var array<string,int> */
    private const ALLOWLIST_BONUS = [
        'Q2133344' => 6,
        'Q40218' => 5,
        'Q26529' => 5,
        'Q26540' => 5,
        'Q6999' => 4,
        'Q318' => 4,
        'Q523' => 4,
        'Q9008' => 4,
        'Q130427' => 4,
    ];

    private int $wikidataCheckedCount = 0;
    private int $wikidataCachedHits = 0;
    private bool $wikidataFailed = false;
    private int $wikidataEntityRequests = 0;

    /**
     * @return array<int, array{stable_key:string,payload:array<string,mixed>}>
     */
    public function fetch(BotSource $source, ?CarbonInterface $date = null): array
    {
        $this->resetDiagnostics();

        $targetDate = ($date ? $date->copy() : now())->startOfDay();
        $requestUrl = $this->buildDailyUrl((string) $source->url, $targetDate);
        $payload = $this->fetchJson($requestUrl);
        $events = $this->extractEvents($payload);
        $selected = $this->selectRelevantEvents($events, $targetDate->toDateString(), $requestUrl, 3, 1);

        $title = sprintf("Dnes v astron\u{00F3}mii (%s)", $targetDate->format('d.m.'));
        $stableKey = 'onthisday:' . $targetDate->toDateString();
        $wikidataStatus = $selected[0]['wikidata_status'] ?? ($this->wikidataFailed ? 'failed' : 'ok');

        if ($selected === []) {
            return [[
                'stable_key' => $stableKey,
                'payload' => [
                    'title' => $title,
                    'summary' => null,
                    'content' => null,
                    'url' => $requestUrl,
                    'published_at' => $targetDate,
                    'fetched_at' => now(),
                    'lang_original' => 'en',
                    'publish_status' => BotPublishStatus::SKIPPED->value,
                    'meta' => [
                        'date' => $targetDate->toDateString(),
                        'source' => self::SOURCE_TAG,
                        'selected_events' => [],
                        'skip_reason' => 'no_relevant_events',
                        'request_url' => $requestUrl,
                        'wikidata_status' => $wikidataStatus,
                        'wikidata_checked_count' => $this->wikidataCheckedCount,
                        'wikidata_cached_hits' => $this->wikidataCachedHits,
                    ],
                ],
            ]];
        }

        $lines = [];
        foreach ($selected as $event) {
            $lines[] = sprintf('- %s - %s (%s)', $event['year_label'], $event['text'], $event['url']);
        }

        return [[
            'stable_key' => $stableKey,
            'payload' => [
                'title' => $title,
                'summary' => implode("\n", $lines),
                'content' => implode("\n", $lines),
                'url' => (string) ($selected[0]['url'] ?? $requestUrl),
                'published_at' => $targetDate,
                'fetched_at' => now(),
                'lang_original' => 'en',
                'meta' => [
                    'date' => $targetDate->toDateString(),
                    'source' => self::SOURCE_TAG,
                    'selected_events' => array_map(static fn (array $event): array => [
                        'event_key' => $event['event_key'],
                        'year' => $event['year'],
                        'text' => $event['text'],
                        'url' => $event['url'],
                        'score' => $event['typed_score'],
                        'keyword_score' => $event['keyword_score'],
                        'typed_score' => $event['typed_score'],
                        'wikidata_bonus' => $event['wikidata_bonus'],
                        'wikidata_status' => $event['wikidata_status'],
                        'page_titles' => $event['page_titles'],
                    ], $selected),
                    'request_url' => $requestUrl,
                    'wikidata_status' => $wikidataStatus,
                    'wikidata_checked_count' => $this->wikidataCheckedCount,
                    'wikidata_cached_hits' => $this->wikidataCachedHits,
                ],
            ],
        ]];
    }

    /**
     * @return array{wikidata_checked_count:int,wikidata_cached_hits:int}
     */
    public function getLastDiagnostics(): array
    {
        return [
            'wikidata_checked_count' => $this->wikidataCheckedCount,
            'wikidata_cached_hits' => $this->wikidataCachedHits,
        ];
    }

    private function resetDiagnostics(): void
    {
        $this->wikidataCheckedCount = 0;
        $this->wikidataCachedHits = 0;
        $this->wikidataFailed = false;
        $this->wikidataEntityRequests = 0;
    }

    /**
     * @return array<string,mixed>
     */
    private function fetchJson(string $url): array
    {
        $timeout = max(1, (int) config('bots.rss_timeout_seconds', 10));
        $retries = max(0, (int) config('bots.rss_retry_times', 2));
        $sleep = max(0, (int) config('bots.rss_retry_sleep_ms', 250));
        $attempts = $retries + 1;
        $userAgent = $this->botUserAgent();

        try {
            $response = Http::secure()
                ->withUserAgent($userAgent)
                ->acceptJson()
                ->timeout($timeout)
                ->retry($attempts, $sleep, null, false)
                ->get($url);
        } catch (Throwable $e) {
            throw new RuntimeException(sprintf(
                'Wikipedia OnThisDay fetch failed (url=%s, status=network_error, snippet="%s")',
                $url,
                $this->snippet($e->getMessage())
            ));
        }

        if (!$response->successful() || !is_array($response->json())) {
            throw new RuntimeException(sprintf(
                'Wikipedia OnThisDay fetch failed (url=%s, status=%d, snippet="%s")',
                $url,
                $response->status(),
                $this->snippet((string) $response->body())
            ));
        }

        return $response->json();
    }

    private function buildDailyUrl(string $baseUrl, CarbonInterface $date): string
    {
        $base = rtrim(trim($baseUrl), '/');
        if ($base === '') {
            throw new RuntimeException('Wikipedia OnThisDay source URL is empty.');
        }

        return sprintf('%s/%02d/%02d', $base, $date->month, $date->day);
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<int,array<string,mixed>>
     */
    private function extractEvents(array $payload): array
    {
        $events = $payload['events'] ?? [];
        return is_array($events) ? array_values($events) : [];
    }

    /**
     * @param array<int,array<string,mixed>> $events
     * @return array<int,array<string,mixed>>
     */
    private function selectRelevantEvents(array $events, string $isoDate, string $fallbackUrl, int $limit, int $minScore): array
    {
        $scored = [];

        foreach ($events as $index => $event) {
            if (!is_array($event)) {
                continue;
            }

            $text = $this->normalizeText((string) ($event['text'] ?? ''));
            if ($text === '') {
                continue;
            }

            $year = is_numeric($event['year'] ?? null) ? (int) $event['year'] : null;
            $pages = is_array($event['pages'] ?? null) ? array_values($event['pages']) : [];
            $pageTitles = [];
            $pagesPayload = [];
            $url = '';

            foreach ($pages as $page) {
                if (!is_array($page)) {
                    continue;
                }

                $title = $this->normalizeText((string) ($page['title'] ?? ''));
                if ($title === '') {
                    continue;
                }

                $pageTitles[] = $title;
                $pageUrl = $this->extractPageUrl($page);
                if ($url === '' && $pageUrl !== '') {
                    $url = $pageUrl;
                }
                $pagesPayload[] = [
                    'title' => $title,
                    'url' => $pageUrl,
                    'pageid' => is_numeric($page['pageid'] ?? null) ? (int) $page['pageid'] : null,
                ];
            }

            $keywordScore = $this->scoreEvent($text, $pageTitles);
            if ($keywordScore < $minScore) {
                continue;
            }

            $scored[] = [
                'event_key' => sprintf('%s:%s:%s', $isoDate, $year !== null ? (string) $year : 'unknown', sha1($text)),
                'year' => $year,
                'year_label' => $year !== null ? (string) $year : '?',
                'text' => $text,
                'url' => $url,
                'page_titles' => $pageTitles,
                'pages' => $pagesPayload,
                'keyword_score' => $keywordScore,
                'typed_score' => $keywordScore,
                'wikidata_bonus' => 0,
                'wikidata_status' => 'not_checked',
                'wikidata_decision' => 'unknown',
                'index' => (int) $index,
            ];
        }

        if ($scored === []) {
            return [];
        }

        usort($scored, static fn (array $a, array $b): int => $b['keyword_score'] <=> $a['keyword_score'] ?: ($a['index'] <=> $b['index']));
        $typed = $this->applyWikidataTyping($scored);
        usort($typed, static fn (array $a, array $b): int => $b['typed_score'] <=> $a['typed_score'] ?: ($a['index'] <=> $b['index']));

        $allowed = array_values(array_filter($typed, static fn (array $event): bool => $event['wikidata_decision'] === 'allow'));
        if ($allowed === [] && $this->wikidataFailed && (int) ($typed[0]['keyword_score'] ?? 0) >= max(1, (int) config('bots.wiki_high_keyword_threshold', 4))) {
            $fallback = $typed[0];
            $fallback['wikidata_status'] = 'failed_fallback';
            $fallback['wikidata_decision'] = 'allow';
            $fallback['typed_score'] = $fallback['keyword_score'];
            $allowed = [$fallback];
        }

        $selected = array_slice($allowed, 0, max(1, $limit));
        foreach ($selected as &$event) {
            if ($event['url'] === '') {
                $event['url'] = $fallbackUrl;
            }
        }
        unset($event);

        return $selected;
    }

    /**
     * @param array<int,array<string,mixed>> $events
     * @return array<int,array<string,mixed>>
     */
    private function applyWikidataTyping(array $events): array
    {
        $maxCandidates = max(1, (int) config('bots.wiki_max_candidate_pages', 15));
        $checkedPages = 0;
        $classificationMemo = [];

        foreach ($events as $index => $event) {
            $bestDecision = 'unknown';
            $bestBonus = 0;
            $bestStatus = 'not_checked';

            foreach ((array) ($event['pages'] ?? []) as $page) {
                if (!is_array($page) || $checkedPages >= $maxCandidates) {
                    continue;
                }

                $checkedPages++;
                $wikibase = $this->resolveWikibaseForPage($page);
                if ($wikibase === null) {
                    continue;
                }

                $this->wikidataCheckedCount++;
                if (!isset($classificationMemo[$wikibase])) {
                    $classificationMemo[$wikibase] = $this->classifyWikibaseItem($wikibase);
                }

                $classification = $classificationMemo[$wikibase];
                $decision = (string) ($classification['decision'] ?? 'unknown');
                $bonus = (int) ($classification['bonus'] ?? 0);
                $status = (string) ($classification['status'] ?? 'ok');

                if ($decision === 'allow' && ($bestDecision !== 'allow' || $bonus > $bestBonus)) {
                    $bestDecision = 'allow';
                    $bestBonus = $bonus;
                    $bestStatus = $status;
                } elseif ($bestDecision !== 'allow' && $decision === 'deny') {
                    $bestDecision = 'deny';
                    $bestStatus = $status;
                } elseif ($bestStatus === 'not_checked') {
                    $bestStatus = $status;
                }
            }

            $events[$index]['wikidata_decision'] = $bestDecision;
            $events[$index]['wikidata_bonus'] = $bestDecision === 'allow' ? $bestBonus : 0;
            $events[$index]['typed_score'] = (int) $event['keyword_score'] + (int) $events[$index]['wikidata_bonus'];
            $events[$index]['wikidata_status'] = $bestStatus;
        }

        return $events;
    }

    /**
     * @param array{title:string,url:string,pageid:?int} $page
     */
    private function resolveWikibaseForPage(array $page): ?string
    {
        $title = $this->normalizeText((string) ($page['title'] ?? ''));
        if ($title === '') {
            return null;
        }

        $titleKey = 'bots:wikidata:wikibase:title:' . sha1($this->lower($title));
        $pageId = $page['pageid'] ?? null;
        $pageKey = $pageId !== null ? 'bots:wikidata:wikibase:page:' . $pageId : null;
        $cached = $pageKey ? Cache::get($pageKey) : null;
        if (!is_array($cached)) {
            $cached = Cache::get($titleKey);
        }
        if (is_array($cached) && array_key_exists('wikibase_item', $cached)) {
            return $this->nullableUpperQid($cached['wikibase_item'] ?? null);
        }

        try {
            $response = Http::secure()
                ->withUserAgent($this->botUserAgent())
                ->acceptJson()
                ->timeout(max(1, (int) config('bots.rss_timeout_seconds', 10)))
                ->get((string) config('bots.wikipedia_mediawiki_api_url', 'https://en.wikipedia.org/w/api.php'), [
                    'action' => 'query',
                    'prop' => 'pageprops',
                    'format' => 'json',
                    'formatversion' => 2,
                    'titles' => $title,
                ]);
            if (!$response->successful() || !is_array($response->json())) {
                throw new RuntimeException('mediawiki_pageprops_failed');
            }
            $pages = data_get($response->json(), 'query.pages', []);
            if (!is_array($pages)) {
                $pages = [];
            }
            if (!array_is_list($pages)) {
                $pages = array_values($pages);
            }
            $wikibase = $this->nullableUpperQid(data_get($pages, '0.pageprops.wikibase_item'));
            Cache::put($titleKey, ['wikibase_item' => $wikibase], $this->cacheTtlDate());
            if ($pageKey) {
                Cache::put($pageKey, ['wikibase_item' => $wikibase], $this->cacheTtlDate());
            }
            return $wikibase;
        } catch (Throwable) {
            $this->wikidataFailed = true;
            return null;
        }
    }

    /**
     * @return array{decision:string,bonus:int,status:string}
     */
    private function classifyWikibaseItem(string $wikibaseItem): array
    {
        $cacheKey = 'bots:wikidata:classification:' . strtoupper(trim($wikibaseItem));
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && isset($cached['decision'])) {
            $this->wikidataCachedHits++;
            return $cached;
        }

        $classification = $this->computeClassification($wikibaseItem);
        Cache::put($cacheKey, $classification, $this->cacheTtlDate());
        return $classification;
    }

    /**
     * @return array{decision:string,bonus:int,status:string}
     */
    private function computeClassification(string $wikibaseItem): array
    {
        try {
            $entities = $this->fetchWikidataEntities([$wikibaseItem]);
        } catch (Throwable) {
            return ['decision' => 'unknown', 'bonus' => 0, 'status' => 'failed'];
        }

        $entity = $entities[strtoupper(trim($wikibaseItem))] ?? [];
        $p31 = $this->extractClaimQids($entity, 'P31');
        $allow = $this->allowlistQids();
        $deny = $this->denylistQids();

        foreach ($p31 as $qid) {
            if (in_array($qid, $deny, true)) {
                return ['decision' => 'deny', 'bonus' => 0, 'status' => 'ok'];
            }
            if (in_array($qid, $allow, true)) {
                return ['decision' => 'allow', 'bonus' => self::ALLOWLIST_BONUS[$qid] ?? 2, 'status' => 'ok'];
            }
        }

        $frontier = $p31;
        $visited = [];
        for ($depth = 1; $depth <= self::MAX_SUBCLASS_DEPTH; $depth++) {
            $frontier = array_values(array_diff(array_unique($frontier), $visited));
            if ($frontier === []) {
                break;
            }
            $visited = array_values(array_unique(array_merge($visited, $frontier)));

            try {
                $entitiesAtDepth = $this->fetchWikidataEntities($frontier);
            } catch (Throwable) {
                return ['decision' => 'unknown', 'bonus' => 0, 'status' => 'failed'];
            }

            $next = [];
            foreach ($frontier as $qid) {
                if (in_array($qid, $allow, true)) {
                    return ['decision' => 'allow', 'bonus' => self::ALLOWLIST_BONUS[$qid] ?? 2, 'status' => 'ok'];
                }
                if (in_array($qid, $deny, true)) {
                    return ['decision' => 'deny', 'bonus' => 0, 'status' => 'ok'];
                }
                foreach ($this->extractClaimQids($entitiesAtDepth[$qid] ?? [], 'P279') as $parent) {
                    if (!in_array($parent, $visited, true)) {
                        $next[] = $parent;
                    }
                }
            }
            $frontier = $next;
        }

        return ['decision' => 'unknown', 'bonus' => 0, 'status' => 'ok'];
    }

    /**
     * @param array<int,string> $qids
     * @return array<string,array<string,mixed>>
     */
    private function fetchWikidataEntities(array $qids): array
    {
        $ids = array_values(array_filter(array_map(static fn (string $qid): string => strtoupper(trim($qid)), $qids)));
        if ($ids === []) {
            return [];
        }

        $maxRequests = max(1, (int) config('bots.wiki_max_wikidata_entity_requests', 15));
        if ($this->wikidataEntityRequests >= $maxRequests) {
            $this->wikidataFailed = true;
            throw new RuntimeException('wikidata_request_limit');
        }
        $this->wikidataEntityRequests++;

        try {
            $response = Http::secure()
                ->withUserAgent($this->botUserAgent())
                ->acceptJson()
                ->timeout(max(1, (int) config('bots.rss_timeout_seconds', 10)))
                ->get((string) config('bots.wikidata_api_url', 'https://www.wikidata.org/w/api.php'), [
                    'action' => 'wbgetentities',
                    'format' => 'json',
                    'props' => 'claims',
                    'ids' => implode('|', $ids),
                ]);
        } catch (ConnectionException $e) {
            $this->wikidataFailed = true;
            throw new RuntimeException($this->snippet($e->getMessage()));
        } catch (Throwable $e) {
            $this->wikidataFailed = true;
            throw new RuntimeException($this->snippet($e->getMessage()));
        }

        if (!$response->successful() || !is_array($response->json('entities'))) {
            $this->wikidataFailed = true;
            throw new RuntimeException('wikidata_http_failure');
        }

        $entities = [];
        foreach ((array) $response->json('entities') as $id => $entity) {
            if (is_array($entity)) {
                $entities[strtoupper(trim((string) ($entity['id'] ?? $id)))] = $entity;
            }
        }
        return $entities;
    }

    /**
     * @param array<string,mixed> $entity
     * @return array<int,string>
     */
    private function extractClaimQids(array $entity, string $property): array
    {
        $claims = data_get($entity, 'claims.' . $property, []);
        if (!is_array($claims)) {
            return [];
        }

        $qids = [];
        foreach ($claims as $claim) {
            if (!is_array($claim)) {
                continue;
            }
            $qid = strtoupper(trim((string) data_get($claim, 'mainsnak.datavalue.value.id', '')));
            if ($qid !== '') {
                $qids[] = $qid;
            }
        }
        return array_values(array_unique($qids));
    }

    /**
     * @return array<int,string>
     */
    private function allowlistQids(): array
    {
        $fromConfig = array_values(array_filter(array_map(static fn ($qid): string => strtoupper(trim((string) $qid)), (array) config('bots.wiki_allowlist_qids', []))));
        return $fromConfig !== [] ? $fromConfig : self::DEFAULT_ALLOWLIST_QIDS;
    }

    /**
     * @return array<int,string>
     */
    private function denylistQids(): array
    {
        $fromConfig = array_values(array_filter(array_map(static fn ($qid): string => strtoupper(trim((string) $qid)), (array) config('bots.wiki_denylist_qids', []))));
        return $fromConfig !== [] ? $fromConfig : self::DEFAULT_DENYLIST_QIDS;
    }

    /**
     * @param array<int,string> $pageTitles
     */
    private function scoreEvent(string $text, array $pageTitles): int
    {
        $haystack = $this->lower($text . ' ' . implode(' ', $pageTitles));
        $score = 0;
        foreach (self::KEYWORDS as $keyword) {
            $score += substr_count($haystack, $keyword);
        }
        return $score;
    }

    /**
     * @param array<string,mixed> $page
     */
    private function extractPageUrl(array $page): string
    {
        return trim((string) data_get($page, 'content_urls.desktop.page', ''))
            ?: trim((string) data_get($page, 'content_urls.mobile.page', ''));
    }

    private function normalizeText(string $value): string
    {
        $normalized = trim($value);
        if ($normalized === '') {
            return '';
        }
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode($normalized, ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? $normalized);
    }

    private function lower(string $value): string
    {
        return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    }

    private function snippet(string $value): string
    {
        $normalized = preg_replace('/\s+/u', ' ', trim($value)) ?? '';
        if ($normalized === '') {
            return 'n/a';
        }
        return function_exists('mb_substr') ? mb_substr($normalized, 0, 500) : substr($normalized, 0, 500);
    }

    private function cacheTtlDate(): \DateTimeInterface
    {
        return now()->addDays(max(1, (int) config('bots.wiki_wikidata_cache_ttl_days', 30)));
    }

    private function nullableUpperQid(mixed $value): ?string
    {
        $qid = strtoupper(trim((string) $value));
        return $qid !== '' ? $qid : null;
    }

    private function botUserAgent(): string
    {
        return trim((string) config('bots.rss_user_agent', 'AstroKomunita/Bot RSS Sync'))
            ?: 'AstroKomunita/Bot RSS Sync';
    }
}
