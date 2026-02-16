<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class OllamaRefinementService
{
    private const SYSTEM_PROMPT = <<<'PROMPT'
You are an editor for Slovak astronomy event content.
Improve naturalness and readability while preserving factual meaning.
Never invent facts, dates, times, locations, visibility details, or numeric values.
Always use correct Slovak diacritics.
Write strictly in Slovak (never Czech, never mixed Slovak/Czech).
If Slovak diacritics are applicable, they must be present.
PROMPT;
    private const TITLE_PROMPT_TEMPLATE = <<<'PROMPT'
Refine the Slovak event title for an astronomy audience.
Rules:
- Max 90 characters.
- Natural Slovak.
- Use correct Slovak diacritics.
- Write strictly in Slovak (never Czech).
- If a word normally uses Slovak diacritics, always write it with diacritics.
- No clickbait.
- No emojis.
- Use correct astronomical terminology.
- Avoid literal translation if unnatural.
- Prefer descriptive but concise form.
Examples:
"Peak of the Perseids meteor shower" -> "Maximum meteorického roja Perzeidy"
"Jupiter 3.7 S of Moon" -> "Jupiter 3,7 južne od Mesiaca"
"Regulus 1.4 N of Moon" -> "Regulus 1,4 severne od Mesiaca"
PROMPT;
    private const DESCRIPTION_PROMPT_TEMPLATE = <<<'PROMPT'
Refine the Slovak event description.
Rules:
- Exactly 2 to 3 sentences total.
- Clear, educational tone for a general Slovak audience.
- Use correct Slovak diacritics.
- Write strictly in Slovak (never Czech).
- If a word normally uses Slovak diacritics, always write it with diacritics.
- Explain what the event is, when it happens, how to observe it, and whether equipment is needed.
- Avoid hallucinated dates or data.
- Do not invent numeric values.
- If unsure, keep neutral phrasing.
- If the input does not contain date/time/location, do not add specific date/time/location claims.
- If input does not contain date/time/location, do not add phrases like "v noci", "večer", exact hour, or city names.
- Keep observational advice generic unless the input explicitly includes concrete observing details.
Guardrail: If information is missing, do not fabricate details.
PROMPT;

    private const OUTPUT_PROMPT_TEMPLATE = <<<'PROMPT'
Return valid JSON only with keys:
- refined_title
- refined_description
Do not include markdown or additional keys.
PROMPT;

    private const TITLE_MAX_CHARS = 90;
    private const DESCRIPTION_MAX_CHARS = 1800;
    private const INPUT_TITLE_MAX_CHARS = 320;
    private const INPUT_DESCRIPTION_MAX_CHARS = 2600;
    private const DEFAULT_TIMEOUT_SECONDS = 25;
    private const DEFAULT_TEMPERATURE = 0.3;
    private const DEFAULT_MAX_TOKENS = 420;
    private const LOW_VRAM_MIN_TOKENS = 350;
    private const LOW_VRAM_MAX_TOKENS = 450;
    private const MAX_BACKOFF_ATTEMPTS = 5;
    private const ASCII_DIACRITIC_EXPECTED_WORDS = [
        'meteoricky',
        'meteorickeho',
        'meteoricka',
        'meteoricke',
        'periodicky',
        'periodickeho',
        'periodicka',
        'periodicke',
        'mozne',
        'mozny',
        'mozna',
        'mozneho',
        'volnym',
        'volneho',
        'najlepsie',
        'svetelneho',
        'dalekohlad',
        'binokular',
    ];
    private const DATE_MONTH_PATTERN = '/\\b(január|januar|február|februar|marec|apríl|april|máj|maj|jún|jun|júl|jul|august|september|október|oktober|november|december|january|february|march|april|may|june|july|august|september|october|november|december)\\b/iu';
    private const TIME_OF_DAY_PATTERN = '/\\b(v noci|nocou|večer|vecer|ráno|rano|po polnoci|pred svitaním|pred svitanim|overnight|at night|in the evening|before dawn)\\b/iu';
    private const LOCATION_PATTERN = '/\\b(v|vo|na|nad|pri|in|at|over)\\s+\\p{Lu}[\\p{L}\\-]{2,}\\b/u';
    private const OBSERVATION_DETAIL_PATTERN = '/\\b(ďalekohľad\\w*|dalekohlad\\w*|dalekohled\\w*|teleskop\\w*|binokulár\\w*|binokular\\w*|binoculars?\\w*|montáž\\w*|montaz\\w*|kamera\\w*|fotoaparát\\w*|fotoaparat\\w*|expozíci\\w*|expozici\\w*|filter\\w*|stativ\\w*|statív\\w*|okulár\\w*|okular\\w*|telescope\\w*|camera\\w*|tripod\\w*)\\b/iu';
    private const NUMERIC_TOKEN_PATTERN = '/\\b\\d{1,4}(?:[.,:]\\d{1,4}){0,2}\\b/u';
    private const CZECH_SPECIFIC_CHARS_PATTERN = '/[\\x{011B}\\x{0159}\\x{016F}]/iu';

    private static ?string $detectedGpuName = null;

    public function __construct(
        private readonly OllamaClient $ollamaClient,
    ) {
    }

    /**
     * @return array{refined_title:string,refined_description:?string,used_fallback:bool,model:string,duration_ms:int|null}
     */
    public function refine(
        string $originalEnglishTitle,
        ?string $originalEnglishDescription,
        string $translatedTitle,
        ?string $translatedDescription
    ): array {
        $fallbackTitle = $this->sanitizeInline($translatedTitle, self::TITLE_MAX_CHARS);
        if ($fallbackTitle === '') {
            $fallbackTitle = $this->sanitizeInline($originalEnglishTitle, self::TITLE_MAX_CHARS);
        }
        $fallbackTitle = $this->normalizeAstronomyTitleFromSource($originalEnglishTitle, $fallbackTitle);

        $fallbackDescription = $this->sanitizeDescription($translatedDescription, self::DESCRIPTION_MAX_CHARS);
        if ($fallbackDescription === null && $translatedDescription !== null) {
            $fallbackDescription = '';
        }

        try {
            $prompt = $this->buildPrompt(
                originalEnglishTitle: $originalEnglishTitle,
                originalEnglishDescription: $originalEnglishDescription,
                translatedTitle: $translatedTitle,
                translatedDescription: $translatedDescription
            );

            $result = $this->generateWithProtection($prompt);
        } catch (Throwable $exception) {
            Log::warning('Ollama refinement failed', [
                'message' => $exception->getMessage(),
                'timeout_seconds' => $this->resolveTimeoutSeconds(),
                'model' => $this->resolveModel(),
            ]);

            return [
                'refined_title' => $fallbackTitle,
                'refined_description' => $fallbackDescription,
                'used_fallback' => true,
                'model' => $this->resolveModel(),
                'duration_ms' => null,
            ];
        }

        $parsed = $this->parseJsonObject((string) ($result['text'] ?? ''));
        if ($parsed === []) {
            Log::warning('Ollama refinement returned invalid JSON payload.');

            return [
                'refined_title' => $fallbackTitle,
                'refined_description' => $fallbackDescription,
                'used_fallback' => true,
                'model' => $this->resolveModel(),
                'duration_ms' => null,
            ];
        }

        $usedFallback = false;

        $refinedTitle = $this->sanitizeInline((string) ($parsed['refined_title'] ?? ''), self::TITLE_MAX_CHARS);
        if ($refinedTitle === '') {
            $refinedTitle = $fallbackTitle;
            $usedFallback = true;
        }
        $refinedTitle = $this->normalizeAstronomyTitleFromSource($originalEnglishTitle, $refinedTitle);

        $rawDescription = array_key_exists('refined_description', $parsed)
            ? (string) $parsed['refined_description']
            : null;
        $refinedDescription = $this->sanitizeDescription($rawDescription, self::DESCRIPTION_MAX_CHARS);

        if ($rawDescription !== null && $refinedDescription === null) {
            $refinedDescription = '';
        }

        if ($fallbackDescription === null && $rawDescription === null) {
            $refinedDescription = null;
        } elseif (($refinedDescription === null || $refinedDescription === '') && $fallbackDescription !== null) {
            $refinedDescription = $fallbackDescription;
            $usedFallback = true;
        }

        $titleValidation = $this->validateRefinedTitle(
            originalEnglishTitle: $originalEnglishTitle,
            translatedTitle: $translatedTitle,
            refinedTitle: $refinedTitle
        );

        if (! $titleValidation['valid']) {
            Log::warning('Ollama refinement title rejected; falling back to base title.', [
                'reason' => $titleValidation['reason'],
            ]);

            $refinedTitle = $fallbackTitle;
            $usedFallback = true;
        }

        $descriptionValidation = $this->validateRefinedDescription(
            originalEnglishTitle: $originalEnglishTitle,
            originalEnglishDescription: $originalEnglishDescription,
            translatedTitle: $translatedTitle,
            translatedDescription: $translatedDescription,
            refinedDescription: $refinedDescription
        );

        if (! $descriptionValidation['valid']) {
            Log::warning('Ollama refinement description rejected; falling back to base description.', [
                'reason' => $descriptionValidation['reason'],
            ]);

            $refinedDescription = $fallbackDescription;
            $usedFallback = true;
        }

        return [
            'refined_title' => $refinedTitle,
            'refined_description' => $refinedDescription,
            'used_fallback' => $usedFallback,
            'model' => (string) ($result['model'] ?? $this->resolveModel()),
            'duration_ms' => isset($result['duration_ms']) ? (int) $result['duration_ms'] : null,
        ];
    }

    private function resolveModel(): string
    {
        $model = trim((string) config('ai.ollama_model_name', config('ai.ollama.model', 'mistral')));
        return $model !== '' ? $model : 'mistral';
    }

    private function resolveTimeoutSeconds(): int
    {
        $value = (int) config('ai.ollama_timeout_seconds', self::DEFAULT_TIMEOUT_SECONDS);
        return max(1, $value);
    }

    private function resolveTemperature(): float
    {
        $value = (float) config('ai.ollama_refinement_temperature', self::DEFAULT_TEMPERATURE);
        return min(1.0, max(0.0, $value));
    }

    private function resolveMaxTokens(): int
    {
        $value = (int) config(
            'ai.ollama_max_tokens_description',
            config('ai.ollama_refinement_max_tokens', self::DEFAULT_MAX_TOKENS)
        );

        if ($this->isLowVramGpuProfile()) {
            $value = max(self::LOW_VRAM_MIN_TOKENS, min(self::LOW_VRAM_MAX_TOKENS, $value));
        }

        return max(120, min(1200, $value));
    }

    /**
     * @return array{text:string,model:string,duration_ms:int,raw:array<string,mixed>}
     */
    private function generateWithProtection(string $prompt): array
    {
        $attempts = min(
            self::MAX_BACKOFF_ATTEMPTS,
            max(1, (int) config('ai.ollama_retry_attempts', 3))
        );
        $baseDelayMs = max(100, (int) config('ai.ollama.retry_sleep_ms', 250));
        $lastException = null;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            $this->applyJitterIfNeeded();

            try {
                return $this->ollamaClient->generate(
                    prompt: $prompt,
                    system: self::SYSTEM_PROMPT,
                    options: [
                        'model' => $this->resolveModel(),
                        'temperature' => $this->resolveTemperature(),
                        'num_predict' => $this->resolveMaxTokens(),
                        'timeout' => $this->resolveTimeoutSeconds(),
                    ]
                );
            } catch (Throwable $exception) {
                $lastException = $exception;

                if (! $this->isRetryableOverloadOrConnection($exception) || $attempt >= $attempts) {
                    throw $exception;
                }

                $delayMs = min(4_000, (int) ($baseDelayMs * (2 ** ($attempt - 1))));
                $delayMs += random_int(0, 120);
                usleep($delayMs * 1_000);
            }
        }

        throw $lastException ?? new \RuntimeException('Ollama refinement failed.');
    }

    private function applyJitterIfNeeded(): void
    {
        $concurrency = max(1, (int) config('ai.ollama_runtime_concurrency', 1));
        if ($concurrency <= 1) {
            return;
        }

        [$minMs, $maxMs] = $this->resolveJitterRangeMs();
        if ($maxMs <= 0) {
            return;
        }

        $delayMs = $minMs >= $maxMs ? $maxMs : random_int($minMs, $maxMs);
        usleep($delayMs * 1_000);
    }

    /**
     * @return array{0:int,1:int}
     */
    private function resolveJitterRangeMs(): array
    {
        $configured = config('ai.ollama_jitter_ms', [200, 500]);

        if (is_numeric($configured)) {
            $value = max(0, (int) $configured);
            return [$value, $value];
        }

        if (is_string($configured)) {
            $parts = array_values(array_filter(array_map(
                static fn (string $item): int => max(0, (int) trim($item)),
                explode(',', $configured)
            ), static fn (int $item): bool => $item >= 0));
        } elseif (is_array($configured)) {
            $parts = array_values(array_map(static fn ($item): int => max(0, (int) $item), $configured));
        } else {
            $parts = [];
        }

        if ($parts === []) {
            return [200, 500];
        }

        $min = $parts[0];
        $max = $parts[1] ?? $parts[0];

        if ($min > $max) {
            [$min, $max] = [$max, $min];
        }

        return [$min, $max];
    }

    private function isRetryableOverloadOrConnection(Throwable $exception): bool
    {
        foreach ($this->exceptionChain($exception) as $item) {
            if ($item instanceof OllamaClientException) {
                $errorCode = $item->errorCode();
                if (in_array($errorCode, ['ollama_connection_error', 'ollama_service_error'], true)) {
                    return true;
                }

                if (str_starts_with($errorCode, 'ollama_http_')) {
                    $status = (int) substr($errorCode, strlen('ollama_http_'));
                    return $status === 429 || $status >= 500;
                }
            }

            $message = strtolower($item->getMessage());
            if (
                str_contains($message, 'connection')
                || str_contains($message, 'timeout')
                || str_contains($message, 'timed out')
                || str_contains($message, 'overload')
                || str_contains($message, 'busy')
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int,Throwable>
     */
    private function exceptionChain(Throwable $exception): array
    {
        $chain = [];
        $current = $exception;

        while ($current !== null) {
            $chain[] = $current;
            $current = $current->getPrevious();
        }

        return $chain;
    }

    private function isLowVramGpuProfile(): bool
    {
        $gpuName = $this->detectGpuName();
        if ($gpuName === '') {
            return false;
        }

        return str_contains($gpuName, 'gtx 1650');
    }

    private function detectGpuName(): string
    {
        if (self::$detectedGpuName !== null) {
            return self::$detectedGpuName;
        }

        if (PHP_OS_FAMILY !== 'Windows' || ! function_exists('shell_exec')) {
            self::$detectedGpuName = '';
            return self::$detectedGpuName;
        }

        $output = @shell_exec('wmic path win32_VideoController get Name /value 2>NUL');
        if (! is_string($output) || trim($output) === '') {
            self::$detectedGpuName = '';
            return self::$detectedGpuName;
        }

        foreach (preg_split('/\r\n|\r|\n/', trim($output)) as $line) {
            if (! is_string($line) || ! str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = array_map('trim', explode('=', $line, 2));
            if (strcasecmp($key, 'Name') === 0 && $value !== '') {
                self::$detectedGpuName = strtolower($value);
                return self::$detectedGpuName;
            }
        }

        self::$detectedGpuName = '';
        return self::$detectedGpuName;
    }

    private function buildPrompt(
        string $originalEnglishTitle,
        ?string $originalEnglishDescription,
        string $translatedTitle,
        ?string $translatedDescription
    ): string {
        $payload = json_encode([
            'original_english_title' => $this->sanitizeInline($originalEnglishTitle, self::INPUT_TITLE_MAX_CHARS),
            'original_english_description' => $this->sanitizeInline((string) ($originalEnglishDescription ?? ''), self::INPUT_DESCRIPTION_MAX_CHARS),
            'translated_title' => $this->sanitizeInline($translatedTitle, self::INPUT_TITLE_MAX_CHARS),
            'translated_description' => $this->sanitizeDescription($translatedDescription, self::INPUT_DESCRIPTION_MAX_CHARS) ?? '',
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $jsonPayload = is_string($payload) ? $payload : '{}';

        return self::TITLE_PROMPT_TEMPLATE
            . "\n\n"
            . self::DESCRIPTION_PROMPT_TEMPLATE
            . "\n\n"
            . self::OUTPUT_PROMPT_TEMPLATE
            . "\n\nInput JSON:\n"
            . $jsonPayload;
    }

    /**
     * @return array<string,mixed>
     */
    private function parseJsonObject(string $value): array
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return [];
        }

        $decoded = json_decode($trimmed, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/\{.*\}/s', $trimmed, $matches) === 1) {
            $decoded = json_decode((string) $matches[0], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    private function sanitizeInline(?string $value, int $maxChars): string
    {
        $plain = trim(strip_tags((string) ($value ?? '')));
        if ($plain === '') {
            return '';
        }

        $plain = preg_replace('/\s+/u', ' ', $plain) ?? $plain;
        return Str::limit(trim($plain), $maxChars, '');
    }

    private function sanitizeDescription(?string $value, int $maxChars): ?string
    {
        if ($value === null) {
            return null;
        }

        $plain = trim(strip_tags($value));
        if ($plain === '') {
            return '';
        }

        $plain = str_replace(["\r\n", "\r"], "\n", $plain);
        $plain = preg_replace('/[ \t]+/u', ' ', $plain) ?? $plain;
        $plain = preg_replace("/\n{3,}/u", "\n\n", $plain) ?? $plain;

        $segments = preg_split("/\n\s*\n/u", $plain) ?: [];
        $paragraphs = [];
        foreach ($segments as $segment) {
            $normalized = trim(preg_replace('/\s+/u', ' ', (string) $segment) ?? (string) $segment);
            if ($normalized !== '') {
                $paragraphs[] = $normalized;
            }
        }

        if ($paragraphs === []) {
            return '';
        }

        $paragraphs = array_slice($paragraphs, 0, 4);
        $joined = implode("\n\n", $paragraphs);
        return Str::limit($joined, $maxChars, '');
    }

    private function normalizeAstronomyTitleFromSource(string $originalEnglishTitle, string $candidateTitle): string
    {
        $phaseMap = [
            'FULL MOON' => 'Spln Mesiaca',
            'NEW MOON' => 'Nov Mesiaca',
            'FIRST QUARTER MOON' => "Prv\u{00E1} \u{0161}tvr\u{0165} Mesiaca",
            'LAST QUARTER MOON' => "Posledn\u{00E1} \u{0161}tvr\u{0165} Mesiaca",
        ];

        $upperOriginal = strtoupper(trim(preg_replace('/\s+/u', ' ', $originalEnglishTitle) ?? $originalEnglishTitle));
        if (isset($phaseMap[$upperOriginal])) {
            return $phaseMap[$upperOriginal];
        }

        if (preg_match('/^\s*(?<object>.+?)\s+(?<distance>\d+(?:[.,]\d+)?)\x{00B0}\s*(?<dir>[NSEW])\s+of\s+Moon\s*$/iu', $originalEnglishTitle, $matches) === 1) {
            $direction = strtoupper((string) ($matches['dir'] ?? ''));
            $directionMap = [
                'N' => 'severne',
                'S' => "ju\u{017E}ne",
                'E' => "v\u{00FD}chodne",
                'W' => "z\u{00E1}padne",
            ];

            if (isset($directionMap[$direction])) {
                $object = $this->sanitizeInline((string) ($matches['object'] ?? ''), 48);
                $distance = str_replace('.', ',', (string) ($matches['distance'] ?? ''));
                if ($object !== '' && $distance !== '') {
                    return Str::limit(sprintf("%s %s\u{00B0} %s od Mesiaca", $object, $distance, $directionMap[$direction]), self::TITLE_MAX_CHARS, '');
                }
            }
        }

        return $candidateTitle;
    }

    /**
     * @return array{valid:bool,reason:string}
     */
    private function validateRefinedTitle(
        string $originalEnglishTitle,
        string $translatedTitle,
        string $refinedTitle
    ): array {
        if ($this->hasHighAsciiSlovakRatio($refinedTitle, null)) {
            return [
                'valid' => false,
                'reason' => 'diacritics_quality_failed',
            ];
        }

        if ($this->containsCzechSpecificChars($refinedTitle)) {
            return [
                'valid' => false,
                'reason' => 'czech_language_artifacts',
            ];
        }

        $titleInputContext = implode("\n", array_filter([
            $originalEnglishTitle,
            $translatedTitle,
        ], static fn (?string $value): bool => $value !== null && trim($value) !== ''));

        if ($this->introducesUnknownNumericTokens($titleInputContext, $refinedTitle)) {
            return [
                'valid' => false,
                'reason' => 'anti_hallucination_failed',
            ];
        }

        return [
            'valid' => true,
            'reason' => 'ok',
        ];
    }

    /**
     * @return array{valid:bool,reason:string}
     */
    private function validateRefinedDescription(
        string $originalEnglishTitle,
        ?string $originalEnglishDescription,
        string $translatedTitle,
        ?string $translatedDescription,
        ?string $refinedDescription
    ): array {
        if ($refinedDescription === null || trim($refinedDescription) === '') {
            return [
                'valid' => true,
                'reason' => 'ok',
            ];
        }

        if ($this->hasHighAsciiSlovakRatio('', $refinedDescription)) {
            return [
                'valid' => false,
                'reason' => 'diacritics_quality_failed',
            ];
        }

        if ($this->containsCzechSpecificChars($refinedDescription)) {
            return [
                'valid' => false,
                'reason' => 'czech_language_artifacts',
            ];
        }

        $sentenceCount = $this->countSentences($refinedDescription);
        if ($sentenceCount < 2 || $sentenceCount > 3) {
            return [
                'valid' => false,
                'reason' => 'sentence_count_out_of_range',
            ];
        }

        $inputContext = implode("\n", array_filter([
            $originalEnglishTitle,
            $originalEnglishDescription,
            $translatedTitle,
            $translatedDescription,
        ], static fn (?string $value): bool => $value !== null && trim($value) !== ''));

        if ($this->hasUnsupportedSpecifics($inputContext, $refinedDescription, $refinedDescription)) {
            return [
                'valid' => false,
                'reason' => 'anti_hallucination_failed',
            ];
        }

        return [
            'valid' => true,
            'reason' => 'ok',
        ];
    }

    private function countSentences(string $text): int
    {
        $parts = preg_split('/(?<=[.!?])\s+/u', trim($text)) ?: [];
        $parts = array_values(array_filter(
            array_map(static fn (string $item): string => trim($item), $parts),
            static fn (string $item): bool => $item !== ''
        ));

        return count($parts);
    }

    private function hasHighAsciiSlovakRatio(string $title, ?string $description): bool
    {
        $text = mb_strtolower(trim($title . ' ' . (string) ($description ?? '')));
        if ($text === '') {
            return false;
        }

        preg_match_all('/[\p{L}]{3,}/u', $text, $matches);
        $words = $matches[0] ?? [];
        $totalWords = count($words);

        if ($totalWords < 4) {
            return false;
        }

        $asciiWords = 0;
        $suspectWords = 0;

        foreach ($words as $word) {
            if (preg_match('/^[a-z]+$/', $word) === 1) {
                $asciiWords++;
            }

            if (in_array($word, self::ASCII_DIACRITIC_EXPECTED_WORDS, true)) {
                $suspectWords++;
            }
        }

        $hasDiacritics = preg_match('/[\x{00E1}\x{00E4}\x{010D}\x{010F}\x{00E9}\x{00ED}\x{013A}\x{013E}\x{0148}\x{00F3}\x{00F4}\x{0155}\x{0161}\x{0165}\x{00FA}\x{00FD}\x{017E}]/u', $text) === 1;
        $asciiRatio = $asciiWords / $totalWords;
        $suspectRatio = $suspectWords / $totalWords;

        if (! $hasDiacritics && $asciiRatio >= 0.70) {
            return true;
        }

        if ($suspectWords >= 2 && $suspectRatio >= 0.12) {
            return true;
        }

        return false;
    }

    private function containsCzechSpecificChars(string $text): bool
    {
        if (trim($text) === '') {
            return false;
        }

        return preg_match(self::CZECH_SPECIFIC_CHARS_PATTERN, $text) === 1;
    }

    private function hasUnsupportedSpecifics(string $inputContext, string $outputContext, ?string $refinedDescription): bool
    {
        if ($outputContext === '') {
            return false;
        }

        if ($this->introducesUnknownNumericTokens($inputContext, $outputContext)) {
            return true;
        }

        $inputHasDateTimeOrLocation = $this->containsDateTimeOrLocationSpecifics($inputContext);
        $outputHasDateTimeOrLocation = $this->containsDateTimeOrLocationSpecifics($outputContext);

        if (! $inputHasDateTimeOrLocation && $outputHasDateTimeOrLocation) {
            return true;
        }

        $inputHasObservationDetails = $this->containsObservationDetails($inputContext);
        $outputHasObservationDetails = $this->containsObservationDetails((string) ($refinedDescription ?? ''));

        if (! $inputHasObservationDetails && $outputHasObservationDetails) {
            return true;
        }

        return false;
    }

    private function introducesUnknownNumericTokens(string $inputContext, string $outputContext): bool
    {
        $inputTokens = $this->extractNumericTokens($inputContext);
        $outputTokens = $this->extractNumericTokens($outputContext);

        if ($outputTokens === []) {
            return false;
        }

        $inputLookup = array_fill_keys($inputTokens, true);

        foreach ($outputTokens as $token) {
            if (! isset($inputLookup[$token])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int,string>
     */
    private function extractNumericTokens(string $text): array
    {
        $normalizedText = str_replace(["\u{00A0}", "\u{00B0}", "\u{00BA}"], [' ', '', ''], $text);
        $normalizedText = preg_replace('/(?<=\d)\s+(?=\d{3}\b)/u', '', $normalizedText) ?? $normalizedText;
        $normalizedText = preg_replace('/(?<=\d)[NSEWnsew](?=\b)/u', '', $normalizedText) ?? $normalizedText;

        preg_match_all(self::NUMERIC_TOKEN_PATTERN, $normalizedText, $matches);

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

    private function normalizeNumericToken(string $token): string
    {
        $value = strtolower(trim($token));
        if ($value === '') {
            return '';
        }

        if (str_contains($value, ':')) {
            $parts = array_map(static fn (string $part): string => (string) (int) $part, explode(':', $value));
            return implode(':', $parts);
        }

        $value = str_replace(',', '.', $value);
        $value = preg_replace('/\s+/u', '', $value) ?? $value;

        if (str_contains($value, '.')) {
            [$intPart, $fractionPart] = explode('.', $value, 2);
            $normalizedInt = (string) (int) $intPart;
            $normalizedFraction = preg_replace('/[^0-9]/', '', $fractionPart) ?? $fractionPart;

            if ($normalizedFraction === '') {
                return $normalizedInt;
            }

            return $normalizedInt . '.' . $normalizedFraction;
        }

        if (preg_match('/^\d+$/', $value) !== 1) {
            return '';
        }

        return (string) (int) $value;
    }

    private function containsDateTimeOrLocationSpecifics(string $text): bool
    {
        if (trim($text) === '') {
            return false;
        }

        if (preg_match('/\\b\\d{1,2}\\s*(?:h|hod(?:\\.|ina|iny|in)?)\\b/iu', $text) === 1) {
            return true;
        }

        if (preg_match(self::DATE_MONTH_PATTERN, $text) === 1) {
            return true;
        }

        if (preg_match(self::TIME_OF_DAY_PATTERN, $text) === 1) {
            return true;
        }

        if (preg_match('/\b\d{1,2}:\d{2}\b/u', $text) === 1) {
            return true;
        }

        if (preg_match('/\b\d{1,2}\.\d{1,2}\.(?:\d{2}|\d{4})\b/u', $text) === 1) {
            return true;
        }

        if (preg_match(self::LOCATION_PATTERN, $text) === 1) {
            return true;
        }

        return false;
    }

    private function containsObservationDetails(string $text): bool
    {
        if (trim($text) === '') {
            return false;
        }

        if (preg_match(self::OBSERVATION_DETAIL_PATTERN, $text) === 1) {
            return true;
        }

        if (preg_match('/\b\d{1,3}\s?(x|mm|cm)\b/iu', $text) === 1) {
            return true;
        }

        return false;
    }
}
