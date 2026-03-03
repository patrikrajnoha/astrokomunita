<?php

namespace App\Services\Events;

use App\Services\Admin\AiLastRunStore;
use App\Services\AI\JsonGuard;
use App\Services\AI\OllamaClient;
use Throwable;

class EventTitlePostEditService
{
    private const FEATURE_NAME = 'event_title_postedit';
    private const SYSTEM_PROMPT = 'Si slovenský editor astronomických názvov. Preformuluj doslovný preklad do prirodzenej slovenčiny. Nepridávaj fakty.';
    private const ISO_TIMESTAMP_PATTERN = '/\b\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(?::\d{2})?(?:Z|[+\-]\d{2}:\d{2})?\b/u';
    private const NUMERIC_TOKEN_PATTERN = '/\b\d{1,4}(?:[.,:]\d{1,4}){0,2}\b/u';

    public function __construct(
        private readonly OllamaClient $ollamaClient,
        private readonly JsonGuard $jsonGuard,
        private readonly AiLastRunStore $lastRunStore,
    ) {
    }

    /**
     * @param array<string,mixed> $context
     * @return array{
     *   status:'success'|'fallback'|'error',
     *   title_sk:string,
     *   fallback_used:bool,
     *   latency_ms:int,
     *   retry_count:int
     * }
     */
    public function postEditTitle(
        ?string $originalEn,
        string $literalSk,
        ?int $eventId = null,
        array $context = [],
        ?string $fallbackTitle = null
    ): array {
        $startedAt = microtime(true);
        $literal = $this->sanitizeTitle($literalSk);
        $fallback = $this->sanitizeTitle($fallbackTitle ?? $literalSk);

        if ($fallback === '') {
            $fallback = $literal;
        }

        if ($literal === '') {
            return $this->finalize(
                status: 'fallback',
                titleSk: $fallback,
                fallbackUsed: true,
                latencyMs: (int) round((microtime(true) - $startedAt) * 1000),
                retryCount: 0,
                eventId: $eventId
            );
        }

        $prompt = $this->buildPrompt(
            originalEn: $originalEn,
            literalSk: $literal,
            context: $this->normalizeContext($context)
        );

        try {
            $response = $this->ollamaClient->generate(
                prompt: $prompt,
                system: self::SYSTEM_PROMPT,
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
                titleSk: $fallback,
                fallbackUsed: true,
                latencyMs: (int) round((microtime(true) - $startedAt) * 1000),
                retryCount: 0,
                eventId: $eventId
            );
        }

        $latencyMs = isset($response['duration_ms'])
            ? max(0, (int) $response['duration_ms'])
            : (int) round((microtime(true) - $startedAt) * 1000);
        $retryCount = max(0, (int) ($response['retry_count'] ?? 0));
        $guardResult = $this->jsonGuard->parseAndValidate(
            (string) ($response['text'] ?? ''),
            ['title_sk' => $this->resolveMaxTitleLength()],
            false
        );

        if (! (bool) ($guardResult['valid'] ?? false)) {
            return $this->finalize(
                status: 'fallback',
                titleSk: $fallback,
                fallbackUsed: true,
                latencyMs: $latencyMs,
                retryCount: $retryCount,
                eventId: $eventId
            );
        }

        $candidateTitle = $this->sanitizeTitle((string) (($guardResult['data'] ?? [])['title_sk'] ?? ''));
        $validationError = $this->validateCandidateTitle($literal, $candidateTitle);

        if ($validationError !== null) {
            return $this->finalize(
                status: 'fallback',
                titleSk: $fallback,
                fallbackUsed: true,
                latencyMs: $latencyMs,
                retryCount: $retryCount,
                eventId: $eventId
            );
        }

        return $this->finalize(
            status: 'success',
            titleSk: $candidateTitle,
            fallbackUsed: false,
            latencyMs: $latencyMs,
            retryCount: $retryCount,
            eventId: $eventId
        );
    }

    /**
     * @param array<string,mixed> $context
     */
    private function buildPrompt(?string $originalEn, string $literalSk, array $context): string
    {
        $payload = [
            'literal_sk' => $this->sanitizeTitle($literalSk),
        ];

        $normalizedOriginalEn = $this->sanitizeTitle((string) ($originalEn ?? ''));
        if ($normalizedOriginalEn !== '') {
            $payload['original_en'] = $normalizedOriginalEn;
        }

        if ($context !== []) {
            $payload['context'] = $context;
        }

        $inputJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (! is_string($inputJson) || trim($inputJson) === '') {
            $inputJson = '{}';
        }

        return <<<PROMPT
Uloha:
- Prepis doslovny preklad "literal_sk" do prirodzenej slovenciny.
- Zachovaj fakty, cisla, jednotky a symboly bez zmeny.
- Nepridavaj nove fakty.
- Nepridavaj datumy ani casy, ktore nie su vo vstupe.

Vrat STRICT JSON objekt presne v tvare:
{"title_sk":"..."}

Bez markdownu a bez dalsieho textu.

Input JSON:
{$inputJson}
PROMPT;
    }

    private function validateCandidateTitle(string $literalSk, string $candidateTitle): ?string
    {
        if ($candidateTitle === '') {
            return 'empty_output';
        }

        if ($this->length($candidateTitle) > $this->resolveMaxTitleLength()) {
            return 'max_length_exceeded';
        }

        if (preg_match(self::ISO_TIMESTAMP_PATTERN, $candidateTitle) === 1) {
            return 'iso_timestamp_detected';
        }

        $inputNumbers = $this->extractNumericTokens($literalSk);
        $outputNumbers = $this->extractNumericTokens($candidateTitle);

        if ($inputNumbers === [] && $outputNumbers !== []) {
            return 'unexpected_numbers_added';
        }

        if ($inputNumbers !== [] && $inputNumbers !== $outputNumbers) {
            return 'numbers_changed';
        }

        if (str_contains($literalSk, '°')) {
            if (! str_contains($candidateTitle, '°')) {
                return 'degree_symbol_removed';
            }

            if ($this->extractDegreeTokens($literalSk) !== $this->extractDegreeTokens($candidateTitle)) {
                return 'degree_tokens_changed';
            }
        }

        if ($this->containsKmToken($literalSk)) {
            if (! $this->containsKmToken($candidateTitle)) {
                return 'km_token_removed';
            }

            if ($this->extractKmTokens($literalSk) !== $this->extractKmTokens($candidateTitle)) {
                return 'km_tokens_changed';
            }
        }

        return null;
    }

    /**
     * @return array<int,string>
     */
    private function extractNumericTokens(string $text): array
    {
        preg_match_all(self::NUMERIC_TOKEN_PATTERN, $text, $matches);

        $tokens = [];
        foreach (($matches[0] ?? []) as $token) {
            $normalized = $this->normalizeNumericToken((string) $token);
            if ($normalized !== '') {
                $tokens[] = $normalized;
            }
        }

        $tokens = array_values(array_unique($tokens));
        sort($tokens);

        return $tokens;
    }

    /**
     * @return array<int,string>
     */
    private function extractDegreeTokens(string $text): array
    {
        preg_match_all('/([0-9][0-9\s.,]*)\s*°/u', $text, $matches);

        $tokens = [];
        foreach (($matches[1] ?? []) as $token) {
            $normalized = $this->normalizeNumericToken((string) $token);
            if ($normalized !== '') {
                $tokens[] = $normalized;
            }
        }

        $tokens = array_values(array_unique($tokens));
        sort($tokens);

        return $tokens;
    }

    /**
     * @return array<int,string>
     */
    private function extractKmTokens(string $text): array
    {
        preg_match_all('/([0-9][0-9\s.,]*)\s*km\b/iu', $text, $matches);

        $tokens = [];
        foreach (($matches[1] ?? []) as $token) {
            $normalized = $this->normalizeNumericToken((string) $token);
            if ($normalized !== '') {
                $tokens[] = $normalized;
            }
        }

        $tokens = array_values(array_unique($tokens));
        sort($tokens);

        return $tokens;
    }

    private function containsKmToken(string $value): bool
    {
        return preg_match('/\bkm\b/iu', $value) === 1;
    }

    private function normalizeNumericToken(string $token): string
    {
        $value = trim($token);
        if ($value === '') {
            return '';
        }

        $value = str_replace("\u{00A0}", ' ', $value);
        $value = preg_replace('/\s+/u', '', $value) ?? $value;

        if (str_contains($value, ':')) {
            $parts = array_map(
                static fn (string $part): string => (string) max(0, (int) $part),
                explode(':', $value)
            );

            return implode(':', $parts);
        }

        $value = str_replace(',', '.', $value);
        $value = preg_replace('/[^0-9.]/', '', $value) ?? $value;

        if ($value === '' || ! preg_match('/^\d+(?:\.\d+)?$/', $value)) {
            return '';
        }

        if (! str_contains($value, '.')) {
            return (string) (int) $value;
        }

        $floatValue = (float) $value;
        $normalized = rtrim(rtrim(sprintf('%.10F', $floatValue), '0'), '.');

        return $normalized !== '' ? $normalized : '0';
    }

    private function sanitizeTitle(string $value): string
    {
        $plain = trim(strip_tags($value));
        if ($plain === '') {
            return '';
        }

        $plain = preg_replace('/\s+/u', ' ', $plain) ?? $plain;
        return trim($plain);
    }

    private function length(string $value): int
    {
        return function_exists('mb_strlen')
            ? mb_strlen($value, 'UTF-8')
            : strlen($value);
    }

    /**
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    private function normalizeContext(array $context): array
    {
        $normalized = [];

        foreach ($context as $key => $value) {
            $normalizedKey = strtolower(trim((string) $key));
            $normalizedKey = preg_replace('/[^a-z0-9_]+/i', '_', $normalizedKey) ?? $normalizedKey;
            $normalizedKey = trim((string) $normalizedKey, '_');

            if ($normalizedKey === '') {
                continue;
            }

            if (is_scalar($value) || $value === null) {
                $normalized[$normalizedKey] = $this->sanitizeTitle((string) ($value ?? ''));
                continue;
            }

            if (! is_array($value)) {
                continue;
            }

            $child = [];
            foreach ($value as $childKey => $childValue) {
                if (! (is_scalar($childValue) || $childValue === null)) {
                    continue;
                }

                $safeChildKey = strtolower(trim((string) $childKey));
                $safeChildKey = preg_replace('/[^a-z0-9_]+/i', '_', $safeChildKey) ?? $safeChildKey;
                $safeChildKey = trim((string) $safeChildKey, '_');
                if ($safeChildKey === '') {
                    continue;
                }

                $child[$safeChildKey] = $this->sanitizeTitle((string) ($childValue ?? ''));
            }

            if ($child !== []) {
                $normalized[$normalizedKey] = $child;
            }
        }

        return $normalized;
    }

    private function resolveModel(): string
    {
        $configured = trim((string) config('events.ai.model', config('ai.ollama.model', 'mistral')));
        return $configured !== '' ? $configured : 'mistral';
    }

    private function resolveTemperature(): float
    {
        $configured = (float) config('events.ai.title_postedit_temperature', 0.25);
        return max(0.2, min(0.3, $configured));
    }

    private function resolveMaxTokens(): int
    {
        $configured = (int) config('events.ai.title_postedit_num_predict', 120);
        return max(64, min(220, $configured));
    }

    private function resolveTimeoutSeconds(): int
    {
        $configured = (int) config('events.ai.title_postedit_timeout', config('events.ai.timeout', 25));
        return max(5, min(60, $configured));
    }

    private function resolveRetryBackoffBaseMs(): int
    {
        $configured = (int) config('events.ai.retry_backoff_base_ms', config('ai.ollama.retry_backoff_base_ms', 250));
        return max(50, $configured);
    }

    private function resolveMaxTitleLength(): int
    {
        $configured = (int) config('events.ai.title_postedit_max_length', 120);
        return max(20, min(255, $configured));
    }

    /**
     * @return array{
     *   status:'success'|'fallback'|'error',
     *   title_sk:string,
     *   fallback_used:bool,
     *   latency_ms:int,
     *   retry_count:int
     * }
     */
    private function finalize(
        string $status,
        string $titleSk,
        bool $fallbackUsed,
        int $latencyMs,
        int $retryCount,
        ?int $eventId
    ): array {
        $normalizedStatus = in_array($status, ['success', 'fallback', 'error'], true) ? $status : 'error';
        $normalizedTitle = $this->sanitizeTitle($titleSk);
        $normalizedEventId = $eventId !== null && $eventId > 0 ? (int) $eventId : null;

        $this->lastRunStore->put(
            featureName: self::FEATURE_NAME,
            status: $normalizedStatus,
            latencyMs: max(0, $latencyMs),
            entityId: $normalizedEventId,
            retryCount: max(0, $retryCount)
        );

        return [
            'status' => $normalizedStatus,
            'title_sk' => $normalizedTitle,
            'fallback_used' => $fallbackUsed,
            'latency_ms' => max(0, $latencyMs),
            'retry_count' => max(0, $retryCount),
        ];
    }
}
