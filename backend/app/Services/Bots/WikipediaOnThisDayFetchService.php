<?php

namespace App\Services\Bots;

use App\Enums\BotPublishStatus;
use App\Models\BotSource;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WikipediaOnThisDayFetchService
{
    private const SOURCE_TAG = 'wikipedia_onthisday';

    /** @var array<int,string> */
    private const KEYWORDS = [
        'astronomy',
        'space',
        'planet',
        'comet',
        'asteroid',
        'telescope',
        'satellite',
        'nasa',
        'probe',
        'mission',
        'orbit',
        'galaxy',
        'supernova',
        'moon',
        'mars',
        'jupiter',
        'venus',
        'mercury',
        'uranus',
        'neptune',
        'pluto',
        'eclipse',
        'cosmic',
        'observatory',
        'apollo',
        'iss',
        'spacecraft',
        'rocket',
        'lunar',
        'solar',
        'star',
    ];

    /**
     * @return array<int, array{stable_key:string,payload:array<string,mixed>}>
     */
    public function fetch(BotSource $source, ?CarbonInterface $date = null): array
    {
        $targetDate = ($date ? $date->copy() : now())->startOfDay();
        $requestUrl = $this->buildDailyUrl((string) $source->url, $targetDate);
        $payload = $this->fetchJson($requestUrl);
        $events = $this->extractEvents($payload);
        $selected = $this->selectRelevantEvents($events, $targetDate->toDateString(), $requestUrl, 3, 1);

        $title = sprintf('Dnes v astronómii (%s)', $targetDate->format('d.m.'));
        $stableKey = 'onthisday:' . $targetDate->toDateString();

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
                    ],
                ],
            ]];
        }

        $lines = [];
        foreach ($selected as $event) {
            $lines[] = sprintf(
                '- %s - %s (%s)',
                $event['year_label'],
                $event['text'],
                $event['url']
            );
        }

        $content = implode("\n", $lines);
        $firstUrl = (string) ($selected[0]['url'] ?? $requestUrl);

        return [[
            'stable_key' => $stableKey,
            'payload' => [
                'title' => $title,
                'summary' => $content,
                'content' => $content,
                'url' => $firstUrl,
                'published_at' => $targetDate,
                'fetched_at' => now(),
                'lang_original' => 'en',
                'meta' => [
                    'date' => $targetDate->toDateString(),
                    'source' => self::SOURCE_TAG,
                    'selected_events' => array_map(
                        static fn (array $event): array => [
                            'event_key' => $event['event_key'],
                            'year' => $event['year'],
                            'text' => $event['text'],
                            'url' => $event['url'],
                            'score' => $event['score'],
                            'page_titles' => $event['page_titles'],
                        ],
                        $selected
                    ),
                    'request_url' => $requestUrl,
                ],
            ],
        ]];
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

        try {
            $response = Http::secure()
                ->acceptJson()
                ->timeout($timeoutSeconds)
                ->retry($attempts, $retrySleepMs, null, false)
                ->get($url);
        } catch (ConnectionException $e) {
            throw new RuntimeException(sprintf(
                'Wikipedia OnThisDay fetch failed (url=%s, status=network_error, snippet="%s")',
                $url,
                $this->snippet($e->getMessage())
            ));
        } catch (\Throwable $e) {
            throw new RuntimeException(sprintf(
                'Wikipedia OnThisDay fetch failed (url=%s, status=network_error, snippet="%s")',
                $url,
                $this->snippet($e->getMessage())
            ));
        }

        if (!$response->successful()) {
            throw new RuntimeException(sprintf(
                'Wikipedia OnThisDay fetch failed (url=%s, status=%d, snippet="%s")',
                $url,
                $response->status(),
                $this->snippet((string) $response->body())
            ));
        }

        $json = $response->json();
        if (!is_array($json)) {
            throw new RuntimeException(sprintf(
                'Wikipedia OnThisDay fetch failed (url=%s, status=invalid_json, snippet="%s")',
                $url,
                $this->snippet((string) $response->body())
            ));
        }

        return $json;
    }

    private function buildDailyUrl(string $baseUrl, CarbonInterface $date): string
    {
        $base = rtrim(trim($baseUrl), '/');
        if ($base === '') {
            throw new RuntimeException('Wikipedia OnThisDay source URL is empty.');
        }

        return sprintf('%s/%d/%d', $base, $date->month, $date->day);
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
     * @return array<int,array{
     *   event_key:string,
     *   year:int|null,
     *   year_label:string,
     *   text:string,
     *   url:string,
     *   score:int,
     *   page_titles:array<int,string>,
     *   index:int
     * }>
     */
    private function selectRelevantEvents(
        array $events,
        string $isoDate,
        string $fallbackUrl,
        int $limit,
        int $minScore
    ): array
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
            $url = '';

            foreach ($pages as $page) {
                if (!is_array($page)) {
                    continue;
                }

                $title = $this->normalizeText((string) ($page['title'] ?? ''));
                if ($title !== '') {
                    $pageTitles[] = $title;
                }

                if ($url === '') {
                    $url = $this->extractPageUrl($page);
                }
            }

            $score = $this->scoreEvent($text, $pageTitles);
            if ($score < $minScore) {
                continue;
            }

            $eventStableKey = sprintf(
                '%s:%s:%s',
                $isoDate,
                $year !== null ? (string) $year : 'unknown',
                sha1($text)
            );

            $scored[] = [
                'event_key' => $eventStableKey,
                'year' => $year,
                'year_label' => $year !== null ? (string) $year : '?',
                'text' => $text,
                'url' => $url !== '' ? $url : '',
                'score' => $score,
                'page_titles' => $pageTitles,
                'index' => (int) $index,
            ];
        }

        usort($scored, static function (array $left, array $right): int {
            if ($left['score'] !== $right['score']) {
                return $right['score'] <=> $left['score'];
            }

            return $left['index'] <=> $right['index'];
        });

        $selected = array_slice($scored, 0, max(1, $limit));

        foreach ($selected as &$event) {
            if ($event['url'] === '') {
                $event['url'] = $fallbackUrl;
            }
        }
        unset($event);

        return $selected;
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
        $desktopPage = trim((string) data_get($page, 'content_urls.desktop.page', ''));
        if ($desktopPage !== '') {
            return $desktopPage;
        }

        $mobilePage = trim((string) data_get($page, 'content_urls.mobile.page', ''));
        if ($mobilePage !== '') {
            return $mobilePage;
        }

        return '';
    }

    private function normalizeText(string $value): string
    {
        $normalized = trim($value);
        if ($normalized === '') {
            return '';
        }

        $normalized = html_entity_decode($normalized, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;

        return trim($normalized);
    }

    private function lower(string $value): string
    {
        if (function_exists('mb_strtolower')) {
            return mb_strtolower($value, 'UTF-8');
        }

        return strtolower($value);
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
