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
PROMPT;

    private const TITLE_PROMPT_TEMPLATE = <<<'PROMPT'
Refine the Slovak event title for an astronomy audience.
Rules:
- Max 90 characters.
- Natural Slovak.
- Use correct Slovak diacritics.
- No clickbait.
- No emojis.
- Use correct astronomical terminology.
- Avoid literal translation if unnatural.
- Prefer descriptive but concise form.
Example:
"Peak of the Perseids meteor shower" -> "Maximum meteorického roja Perzeidy"
PROMPT;

    private const DESCRIPTION_PROMPT_TEMPLATE = <<<'PROMPT'
Refine the Slovak event description.
Rules:
- 2 to 4 short paragraphs.
- Clear, educational tone for a general Slovak audience.
- Use correct Slovak diacritics.
- Explain what the event is, when it happens, how to observe it, and whether equipment is needed.
- Avoid hallucinated dates or data.
- Do not invent numeric values.
- If unsure, keep neutral phrasing.
- If the input does not contain date/time/location, do not add specific date/time/location claims.
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
    private const INPUT_DESCRIPTION_MAX_CHARS = 7000;
    private const DEFAULT_TIMEOUT_SECONDS = 25;
    private const DEFAULT_TEMPERATURE = 0.3;
    private const DEFAULT_MAX_TOKENS = 700;
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
    private const DATE_MONTH_PATTERN = '/\b(janu[aá]r|febru[aá]r|marec|apr[ií]l|m[aá]j|j[uú]n|j[uú]l|august|september|okt[oó]ber|november|december|january|february|march|april|may|june|july|august|september|october|november|december)\b/iu';
    private const TIME_OF_DAY_PATTERN = '/\b(v noci|nocou|večer|vecer|ráno|rano|po polnoci|pred svitan[ií]m|overnight|at night|in the evening|before dawn)\b/iu';
    private const LOCATION_PATTERN = '/\b(v|vo|na|nad|pri|in|at|over)\s+[A-ZÁÄČĎÉÍĹĽŇÓÔŔŠŤÚÝŽ][\p{L}\-]{2,}\b/u';
    private const OBSERVATION_DETAIL_PATTERN = '/\b(ďalekohľad|dalekohlad|teleskop|binokul[aá]r|binokular|mont[aá]ž|montaz|kamera|fotoapar[aá]t|expoz[ií]ci[ea]|filter|stat[ií]v|telescope|binoculars?|camera|tripod)\b/iu';
    private const NUMERIC_TOKEN_PATTERN = '/\b\d{1,4}(?:[.:]\d{1,4}){0,2}\b/u';

    public function __construct(
        private readonly OllamaClient $ollamaClient,
    ) {
    }

    /**
     * @return array{refined_title:string,refined_description:?string,used_fallback:bool}
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

            $result = $this->ollamaClient->generate(
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
            Log::warning('Ollama refinement failed', [
                'message' => $exception->getMessage(),
                'timeout_seconds' => $this->resolveTimeoutSeconds(),
                'model' => $this->resolveModel(),
            ]);

            return [
                'refined_title' => $fallbackTitle,
                'refined_description' => $fallbackDescription,
                'used_fallback' => true,
            ];
        }

        $parsed = $this->parseJsonObject((string) ($result['text'] ?? ''));
        if ($parsed === []) {
            Log::warning('Ollama refinement returned invalid JSON payload.');

            return [
                'refined_title' => $fallbackTitle,
                'refined_description' => $fallbackDescription,
                'used_fallback' => true,
            ];
        }

        $usedFallback = false;

        $refinedTitle = $this->sanitizeInline((string) ($parsed['refined_title'] ?? ''), self::TITLE_MAX_CHARS);
        if ($refinedTitle === '') {
            $refinedTitle = $fallbackTitle;
            $usedFallback = true;
        }

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

        $validation = $this->validateRefinedOutput(
            originalEnglishTitle: $originalEnglishTitle,
            originalEnglishDescription: $originalEnglishDescription,
            translatedTitle: $translatedTitle,
            translatedDescription: $translatedDescription,
            refinedTitle: $refinedTitle,
            refinedDescription: $refinedDescription
        );

        if (! $validation['valid']) {
            Log::warning('Ollama refinement output rejected; falling back to base translation.', [
                'reason' => $validation['reason'],
            ]);

            return [
                'refined_title' => $fallbackTitle,
                'refined_description' => $fallbackDescription,
                'used_fallback' => true,
            ];
        }

        return [
            'refined_title' => $refinedTitle,
            'refined_description' => $refinedDescription,
            'used_fallback' => $usedFallback,
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
        $value = (int) config('ai.ollama_refinement_max_tokens', self::DEFAULT_MAX_TOKENS);
        return max(120, min(1200, $value));
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

    /**
     * @return array{valid:bool,reason:string}
     */
    private function validateRefinedOutput(
        string $originalEnglishTitle,
        ?string $originalEnglishDescription,
        string $translatedTitle,
        ?string $translatedDescription,
        string $refinedTitle,
        ?string $refinedDescription
    ): array {
        if ($this->hasHighAsciiSlovakRatio($refinedTitle, $refinedDescription)) {
            return [
                'valid' => false,
                'reason' => 'diacritics_quality_failed',
            ];
        }

        $inputContext = implode("\n", array_filter([
            $originalEnglishTitle,
            $originalEnglishDescription,
            $translatedTitle,
            $translatedDescription,
        ], static fn (?string $value): bool => $value !== null && trim($value) !== ''));

        $outputContext = implode("\n", array_filter([
            $refinedTitle,
            $refinedDescription,
        ], static fn (?string $value): bool => $value !== null && trim($value) !== ''));

        if ($this->hasUnsupportedSpecifics($inputContext, $outputContext, $refinedDescription)) {
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

        $hasDiacritics = preg_match('/[áäčďéíĺľňóôŕšťúýž]/u', $text) === 1;
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
        preg_match_all(self::NUMERIC_TOKEN_PATTERN, $text, $matches);
        $tokens = array_map(
            static fn (string $token): string => strtolower(trim($token)),
            $matches[0] ?? []
        );

        $tokens = array_values(array_unique(array_filter($tokens, static fn (string $token): bool => $token !== '')));
        sort($tokens);

        return $tokens;
    }

    private function containsDateTimeOrLocationSpecifics(string $text): bool
    {
        if (trim($text) === '') {
            return false;
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
