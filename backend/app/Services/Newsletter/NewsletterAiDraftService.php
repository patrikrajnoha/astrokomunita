<?php

namespace App\Services\Newsletter;

use App\Models\Event;
use App\Services\Admin\AiLastRunStore;
use App\Services\AI\JsonGuard;
use App\Services\AI\OllamaClient;
use App\Services\Events\EventInsightsCacheService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Throwable;

class NewsletterAiDraftService
{
    private const FEATURE_NAME = 'newsletter_copy_draft';
    private const FEATURE_ENTITY_ID = 'newsletter';
    private const ISO_TIMESTAMP_PATTERN = '/\b\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(?::\d{2})?(?:Z|[+\-]\d{2}:\d{2})?\b/u';
    private const DIGIT_PATTERN = '/\d/u';

    public function __construct(
        private readonly NewsletterSelectionService $selectionService,
        private readonly EventInsightsCacheService $insightsCache,
        private readonly OllamaClient $ollamaClient,
        private readonly JsonGuard $jsonGuard,
        private readonly AiLastRunStore $lastRunStore,
    ) {
    }

    /**
     * @return array{
     *   status:'success'|'fallback'|'error',
     *   subjects:array<int,string>,
     *   intro:string,
     *   tip_text:string,
     *   fallback_used:bool,
     *   last_run:array<string,mixed>
     * }
     */
    public function generateDraft(): array
    {
        $startedAt = microtime(true);
        $payload = $this->selectionService->buildNewsletterPayload(adminPreview: true);
        $fallbackDraft = $this->fallbackDraft($payload);
        $structuredInput = $this->buildStructuredInput($payload);
        $inputHasDigits = $this->containsDigits($this->serializeForDigitCheck($structuredInput));
        $prompt = $this->buildPrompt($structuredInput);

        try {
            $response = $this->ollamaClient->generate(
                prompt: $prompt,
                system: $this->systemPrompt(),
                options: [
                    'model' => $this->resolveModel(),
                    'temperature' => $this->resolveTemperature(),
                    'num_predict' => $this->resolveMaxTokens(),
                    'timeout' => $this->resolveTimeoutSeconds(),
                    'max_retries' => 2,
                    'retry_backoff_base_ms' => $this->resolveRetryBackoffBaseMs(),
                ]
            );
        } catch (Throwable) {
            return $this->finalize(
                status: 'error',
                draft: $fallbackDraft,
                fallbackUsed: true,
                latencyMs: (int) round((microtime(true) - $startedAt) * 1000),
                retryCount: 0
            );
        }

        $latencyMs = isset($response['duration_ms'])
            ? max(0, (int) $response['duration_ms'])
            : (int) round((microtime(true) - $startedAt) * 1000);
        $retryCount = max(0, (int) ($response['retry_count'] ?? 0));
        $parsed = $this->jsonGuard->parseJsonObject((string) ($response['text'] ?? ''));

        if (! (bool) ($parsed['valid'] ?? false)) {
            return $this->finalize(
                status: 'fallback',
                draft: $fallbackDraft,
                fallbackUsed: true,
                latencyMs: $latencyMs,
                retryCount: $retryCount
            );
        }

        $validation = $this->validateOutput(
            data: (array) ($parsed['data'] ?? []),
            inputHasDigits: $inputHasDigits
        );

        if (! $validation['valid']) {
            return $this->finalize(
                status: 'fallback',
                draft: $fallbackDraft,
                fallbackUsed: true,
                latencyMs: $latencyMs,
                retryCount: $retryCount
            );
        }

        return $this->finalize(
            status: 'success',
            draft: $validation['draft'],
            fallbackUsed: false,
            latencyMs: $latencyMs,
            retryCount: $retryCount
        );
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{
     *   subjects:array<int,string>,
     *   intro:string,
     *   tip_text:string
     * }
     */
    private function fallbackDraft(array $payload): array
    {
        $tip = $this->sanitizeText((string) ($payload['astronomical_tip'] ?? ''), 320);
        if ($tip === '') {
            $tip = 'Tip pripraveny z udalosti.';
        }

        return [
            'subjects' => [
                'Tyzdenny prehlad udalosti',
                'Astronomicky tyzden',
                'Tipy na pozorovanie oblohy',
            ],
            'intro' => 'Vybrali sme pre teba najzaujimavejsie udalosti a clanky na najblizsie dni.',
            'tip_text' => $tip,
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private function buildStructuredInput(array $payload): array
    {
        $events = (array) ($payload['top_events'] ?? []);
        $eventIds = collect($events)
            ->map(static fn (mixed $row): int => (int) data_get($row, 'id', 0))
            ->filter(static fn (int $eventId): bool => $eventId > 0)
            ->unique()
            ->values()
            ->all();

        $eventModels = Event::query()
            ->whereIn('id', $eventIds)
            ->get(['id', 'type', 'start_at'])
            ->keyBy('id');

        $topEvents = [];
        foreach (array_slice($events, 0, 5) as $row) {
            $eventId = (int) data_get($row, 'id', 0);
            $eventModel = $eventId > 0 ? $eventModels->get($eventId) : null;

            $title = $this->sanitizeText((string) data_get($row, 'title', ''), 180);
            if ($title === '') {
                continue;
            }

            $type = $this->sanitizeText((string) ($eventModel?->type ?? data_get($row, 'type', 'other')), 60);
            if ($type === '') {
                $type = 'other';
            }

            $startRaw = (string) data_get($row, 'start_at', '');
            if ($startRaw === '' && $eventModel?->start_at) {
                $startRaw = (string) $eventModel->start_at->toIso8601String();
            }

            $topEvents[] = [
                'title' => $title,
                'type' => $type,
                'start_local_formatted' => $this->formatStartLocal($startRaw),
            ];
        }

        $topArticles = [];
        foreach (array_slice((array) ($payload['top_articles'] ?? []), 0, 4) as $row) {
            $title = $this->sanitizeText((string) data_get($row, 'title', ''), 180);
            $slug = $this->sanitizeSlug((string) data_get($row, 'slug', ''));

            if ($title === '' || $slug === '') {
                continue;
            }

            $topArticles[] = [
                'title' => $title,
                'slug' => $slug,
            ];
        }

        $insights = [];
        foreach ($eventIds as $eventId) {
            $cached = $this->insightsCache->get($eventId);
            if (! is_array($cached)) {
                continue;
            }

            foreach (['how_to_observe', 'why_interesting'] as $key) {
                $candidate = $this->sanitizeInsightSentence((string) ($cached[$key] ?? ''));
                if ($candidate === '') {
                    continue;
                }

                $insights[] = $candidate;
                if (count($insights) >= 2) {
                    break 2;
                }
            }
        }

        $input = [
            'top_events' => $topEvents,
            'top_articles' => $topArticles,
        ];

        if ($insights !== []) {
            $input['insights'] = $insights;
        }

        return $input;
    }

    /**
     * @param array<string,mixed> $input
     */
    private function buildPrompt(array $input): string
    {
        $inputJson = json_encode($input, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (! is_string($inputJson) || trim($inputJson) === '') {
            $inputJson = '{}';
        }

        return <<<PROMPT
Uloha:
- Napis newsletter copy draft v slovencine pre admin preview.
- Stylizuj len text. Nemen fakty zo vstupu.
- Nepridavaj nove datumy, casy, cisla ani tvrdenia, ktore nie su vo vstupe.
- Nevkladaj ISO timestampy.

Vrat STRICT JSON objekt presne v tvare:
{"subjects":["...","...","..."],"intro":"...","tip_text":"..."}

Pravidla:
- subjects: presne 3 polozky, kazda max 80 znakov
- intro: max 280 znakov
- tip_text: max 320 znakov
- bez markdownu, bez komentarov, bez dalsich klucov

Input JSON:
{$inputJson}
PROMPT;
    }

    private function systemPrompt(): string
    {
        return 'Si slovensky newsletter editor. Pises strucne, prirodzene a bez halucinacii.';
    }

    private function formatStartLocal(string $value): string
    {
        $fallback = 'buduci tyzden';
        $raw = trim($value);
        if ($raw === '') {
            return $fallback;
        }

        $timezone = (string) config('events.timezone', config('app.timezone', 'Europe/Bratislava'));

        try {
            return CarbonImmutable::parse($raw)->setTimezone($timezone)->format('d. m. Y H:i');
        } catch (Throwable) {
            return $fallback;
        }
    }

    private function sanitizeInsightSentence(string $value): string
    {
        $text = $this->sanitizeText($value, 240);
        if ($text === '') {
            return '';
        }

        $text = preg_replace('/\b\d{4}-\d{2}-\d{2}(?:[ T]\d{2}:\d{2}(?::\d{2})?)?(?:Z|[+\-]\d{2}:\d{2})?\b/u', '', $text) ?? $text;
        $text = preg_replace('/\b\d{1,2}\.\s*\d{1,2}\.\s*\d{4}\b/u', '', $text) ?? $text;
        $text = preg_replace('/\b\d{1,2}:\d{2}(?::\d{2})?\b/u', '', $text) ?? $text;
        $text = preg_replace('/\b\d+\b/u', '', $text) ?? $text;
        $text = preg_replace('/\s+([,.;:!?])/u', '$1', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        $text = trim($text, " \t\n\r\0\x0B,.;:-");

        return $text !== '' ? Str::limit($text, 220, '') : '';
    }

    private function sanitizeSlug(string $value): string
    {
        $slug = trim($value);
        if ($slug === '') {
            return '';
        }

        $slug = preg_replace('/[#?].*$/', '', $slug) ?? $slug;
        $slug = trim($slug, '/');
        if ($slug === '') {
            return '';
        }

        return Str::limit($slug, 120, '');
    }

    /**
     * @param array<string,mixed> $data
     * @return array{
     *   valid:bool,
     *   draft:array{subjects:array<int,string>,intro:string,tip_text:string}
     * }
     */
    private function validateOutput(array $data, bool $inputHasDigits): array
    {
        $empty = [
            'valid' => false,
            'draft' => [
                'subjects' => [],
                'intro' => '',
                'tip_text' => '',
            ],
        ];

        $keys = array_keys($data);
        sort($keys);
        $expected = ['intro', 'subjects', 'tip_text'];
        if ($keys !== $expected) {
            return $empty;
        }

        $subjectsRaw = $data['subjects'] ?? null;
        $introRaw = $data['intro'] ?? null;
        $tipRaw = $data['tip_text'] ?? null;

        if (! is_array($subjectsRaw) || ! is_string($introRaw) || ! is_string($tipRaw)) {
            return $empty;
        }

        if (count($subjectsRaw) !== 3) {
            return $empty;
        }

        $subjects = [];
        foreach ($subjectsRaw as $subjectRaw) {
            if (! is_string($subjectRaw)) {
                return $empty;
            }

            $subject = $this->sanitizeText($subjectRaw, 80);
            if ($subject === '' || $this->length($subject) > 80) {
                return $empty;
            }

            if ($this->containsIsoTimestamp($subject)) {
                return $empty;
            }

            $subjects[] = $subject;
        }

        $intro = $this->sanitizeText($introRaw, 280);
        $tipText = $this->sanitizeText($tipRaw, 320);

        if ($intro === '' || $tipText === '') {
            return $empty;
        }

        if ($this->containsIsoTimestamp($intro) || $this->containsIsoTimestamp($tipText)) {
            return $empty;
        }

        if (! $inputHasDigits) {
            $outputText = implode("\n", array_merge($subjects, [$intro, $tipText]));
            if ($this->containsDigits($outputText)) {
                return $empty;
            }
        }

        return [
            'valid' => true,
            'draft' => [
                'subjects' => $subjects,
                'intro' => $intro,
                'tip_text' => $tipText,
            ],
        ];
    }

    private function containsIsoTimestamp(string $value): bool
    {
        return preg_match(self::ISO_TIMESTAMP_PATTERN, $value) === 1;
    }

    private function containsDigits(string $value): bool
    {
        return preg_match(self::DIGIT_PATTERN, $value) === 1;
    }

    /**
     * @param array<string,mixed> $input
     */
    private function serializeForDigitCheck(array $input): string
    {
        $serialized = json_encode($input, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return is_string($serialized) ? $serialized : '';
    }

    private function sanitizeText(string $value, int $maxLength): string
    {
        $plain = trim(strip_tags($value));
        if ($plain === '') {
            return '';
        }

        $plain = preg_replace('/\s+/u', ' ', $plain) ?? $plain;
        return Str::limit(trim($plain), max(1, $maxLength), '');
    }

    private function length(string $value): int
    {
        return function_exists('mb_strlen')
            ? mb_strlen($value, 'UTF-8')
            : strlen($value);
    }

    private function resolveModel(): string
    {
        $configured = trim((string) config('events.ai.model', config('ai.ollama.model', 'mistral')));
        return $configured !== '' ? $configured : 'mistral';
    }

    private function resolveTemperature(): float
    {
        $configured = (float) config('events.ai.humanized_temperature', 0.25);
        return max(0.1, min(0.3, $configured));
    }

    private function resolveMaxTokens(): int
    {
        $configured = (int) config('events.ai.humanized_num_predict', 320);
        return max(160, min(420, $configured));
    }

    private function resolveTimeoutSeconds(): int
    {
        $configured = (int) config('events.ai.timeout', 40);
        return max(5, min(90, $configured));
    }

    private function resolveRetryBackoffBaseMs(): int
    {
        $configured = (int) config('events.ai.retry_backoff_base_ms', config('ai.ollama.retry_backoff_base_ms', 250));
        return max(50, $configured);
    }

    /**
     * @param array{subjects:array<int,string>,intro:string,tip_text:string} $draft
     * @return array{
     *   status:'success'|'fallback'|'error',
     *   subjects:array<int,string>,
     *   intro:string,
     *   tip_text:string,
     *   fallback_used:bool,
     *   last_run:array<string,mixed>
     * }
     */
    private function finalize(
        string $status,
        array $draft,
        bool $fallbackUsed,
        int $latencyMs,
        int $retryCount
    ): array {
        $normalizedStatus = in_array($status, ['success', 'fallback', 'error'], true)
            ? $status
            : 'error';

        $lastRun = $this->lastRunStore->put(
            featureName: self::FEATURE_NAME,
            status: $normalizedStatus,
            latencyMs: max(0, $latencyMs),
            entityId: self::FEATURE_ENTITY_ID,
            retryCount: max(0, $retryCount)
        );

        return [
            'status' => $normalizedStatus,
            'subjects' => array_values(array_slice((array) ($draft['subjects'] ?? []), 0, 3)),
            'intro' => $this->sanitizeText((string) ($draft['intro'] ?? ''), 280),
            'tip_text' => $this->sanitizeText((string) ($draft['tip_text'] ?? ''), 320),
            'fallback_used' => $fallbackUsed,
            'last_run' => $lastRun,
        ];
    }
}

