<?php

namespace App\Services\Events;

use App\Models\Event;
use App\Services\AI\OllamaClient;
use App\Services\AI\OllamaClientException;
use App\Support\Http\SslVerificationPolicy;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class EventDescriptionGeneratorService
{
    private const NUMERIC_TOKEN_PATTERN = '/\b\d{1,4}(?:[.,:]\d{1,4}){0,2}\b/u';
    private const CELESTIAL_TERMS = [
        'slnko',
        'mesiac',
        'zem',
        'merkur',
        'venus',
        'mars',
        'jupiter',
        'saturn',
        'uran',
        'neptun',
        'pluto',
        'regulus',
        'spica',
        'antares',
        'pollux',
        'pleiades',
        'plejady',
    ];

    public function __construct(
        private readonly OllamaClient $ollamaClient,
        private readonly EventDescriptionTemplateBuilder $templateBuilder,
    ) {
    }

    /**
     * @return array{ok:bool,endpoint:string,model:string,message:?string}
     */
    public function preflightOllama(): array
    {
        $endpoint = $this->resolveOllamaBaseUrl();
        $model = $this->resolveOllamaModel();
        $timeout = $this->resolveOllamaTimeoutSeconds();
        $verifyOption = app(SslVerificationPolicy::class)->resolveVerifyOption(
            allowInsecure: ! (bool) config('ai.ollama.verify_ssl', true)
        );

        try {
            $response = Http::baseUrl($endpoint)
                ->timeout($timeout)
                ->connectTimeout(min($timeout, 5))
                ->withOptions(['verify' => $verifyOption])
                ->acceptJson()
                ->get('/api/tags');

            if (! $response->successful()) {
                return [
                    'ok' => false,
                    'endpoint' => $endpoint,
                    'model' => $model,
                    'message' => 'HTTP ' . $response->status(),
                ];
            }

            return [
                'ok' => true,
                'endpoint' => $endpoint,
                'model' => $model,
                'message' => null,
            ];
        } catch (ConnectionException $exception) {
            return [
                'ok' => false,
                'endpoint' => $endpoint,
                'model' => $model,
                'message' => $exception->getMessage(),
            ];
        } catch (Throwable $exception) {
            return [
                'ok' => false,
                'endpoint' => $endpoint,
                'model' => $model,
                'message' => $exception->getMessage(),
            ];
        }
    }

    /**
     * @return array{description:string,short:string,provider:string}
     */
    public function generateForEvent(Event $event, ?string $mode = null): array
    {
        $resolvedMode = $this->resolveMode($mode);

        if ($resolvedMode === 'template') {
            return $this->templateBuilder->build($event);
        }

        return $this->generateWithOllama($event);
    }

    /**
     * @return array{description:string,short:string,provider:string}
     */
    private function generateWithOllama(Event $event): array
    {
        $template = $this->templateBuilder->build($event);
        $tz = (string) config('events.timezone', 'Europe/Bratislava');
        $startLocal = $this->formatDateTime($event->start_at, $tz);
        $endLocal = $this->formatDateTime($event->end_at, $tz);
        $maxLocal = $this->formatDateTime($event->max_at, $tz);

        $system = 'Si redaktor astronomickeho kalendara. Pises iba po slovensky so spravnou diakritikou.';
        $prompt = <<<PROMPT
Vytvor JSON s klucmi "description" a "short".
Poziadavky:
- Jazyk: slovencina so spravnou diakritikou
- prepis a obohat BASE_DESCRIPTION, ale nemen jeho fakticky obsah
- bez halucinacii, nemen cisla ani casy
- description: 2-3 plne vety, max 500 znakov
- veta 1: vysvetli, o aky jav ide
- veta 2: preco sa oplati jav sledovat a ako ho pozorovat
- veta 3 (volitelna): kratka zaujimavost pre bezneho pozorovatela
- description musi obsahovat aj informaciu, kedy je jav viditelny; pouzi iba casy/datumy zo vstupu
- ak cas vo vstupe chyba, napis neutralne "Cas viditelnosti zavisi od polohy pozorovatela."
- short: jedna veta, max 180 znakov
- bez markdownu

Vstup:
- title: {$event->title}
- type: {$event->type}
- start_local: {$startLocal}
- end_local: {$endLocal}
- max_local: {$maxLocal}
- region_scope: {$event->region_scope}
- source: {$event->source_name}
- BASE_DESCRIPTION: {$template['description']}
- BASE_SHORT: {$template['short']}

Vrat iba validny JSON.
PROMPT;

        try {
            $result = $this->ollamaClient->generate(
                prompt: $prompt,
                system: $system,
                options: [
                    'model' => (string) config('events.ai.model', config('ai.ollama.model', 'mistral')),
                    'temperature' => (float) config('events.ai.temperature', 0.2),
                    'num_predict' => (int) config('events.ai.num_predict', 420),
                    'timeout' => (int) config('events.ai.timeout', 45),
                ]
            );
        } catch (OllamaClientException $exception) {
            throw new RuntimeException($exception->getMessage(), 0, $exception);
        } catch (Throwable $exception) {
            throw new RuntimeException('Event description generation failed.', 0, $exception);
        }

        $raw = (string) ($result['text'] ?? '');
        $parsed = $this->parseModelJson($raw);

        $description = $this->sanitizeText((string) ($parsed['description'] ?? ''), 500);
        $short = $this->sanitizeText((string) ($parsed['short'] ?? ''), 180);
        $provider = 'ollama';

        if ($description === '' && $short === '') {
            $description = $this->sanitizeText($raw, 500);
        }

        if ($description === '') {
            $description = (string) $template['description'];
            $provider = 'template';
        }

        if (! $this->passesSafetyGuards((string) $event->title, (string) $template['description'], $description)) {
            $description = (string) $template['description'];
            $provider = 'template';
        }

        $description = $this->enrichDescription($description, $startLocal, $endLocal, $maxLocal);

        if ($short === '') {
            $short = Str::limit($description, 180, '');
        }

        if ($provider === 'template') {
            $short = $this->sanitizeText((string) $template['short'], 180);
            if ($short === '') {
                $short = Str::limit($description, 180, '');
            }
        }

        return [
            'description' => $description,
            'short' => $short,
            'provider' => $provider,
        ];
    }

    private function resolveMode(?string $mode): string
    {
        $value = strtolower(trim((string) ($mode ?? '')));
        if ($value === '') {
            $value = strtolower(trim((string) config('events.ai.description_mode', 'template')));
        }

        return in_array($value, ['template', 'ollama'], true) ? $value : 'template';
    }

    private function formatDateTime(mixed $value, string $timezone): string
    {
        if (! $value instanceof CarbonInterface) {
            return 'n/a';
        }

        return $value->clone()->setTimezone($timezone)->format('Y-m-d H:i');
    }

    /**
     * @return array<string,mixed>
     */
    private function parseModelJson(string $text): array
    {
        $value = trim($text);
        if ($value === '') {
            return [];
        }

        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/\{.*\}/s', $value, $matches) === 1) {
            $decoded = json_decode((string) $matches[0], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    private function sanitizeText(string $value, int $maxLength): string
    {
        $plain = trim(strip_tags($value));
        if ($plain === '') {
            return '';
        }

        $plain = preg_replace('/\s+/u', ' ', $plain) ?? $plain;
        return Str::limit(trim($plain), $maxLength, '');
    }

    private function resolveOllamaBaseUrl(): string
    {
        $value = trim((string) config('ai.ollama_base_url', config('ai.ollama.base_url', 'http://127.0.0.1:11434')));
        return $value !== '' ? $value : 'http://127.0.0.1:11434';
    }

    private function resolveOllamaModel(): string
    {
        $value = trim((string) config('ai.ollama_model_name', config('ai.ollama.model', 'mistral')));
        return $value !== '' ? $value : 'mistral';
    }

    private function resolveOllamaTimeoutSeconds(): int
    {
        return max(1, (int) config('ai.ollama_timeout_seconds', config('ai.ollama.timeout', 45)));
    }

    private function enrichDescription(string $description, string $startLocal, string $endLocal, string $maxLocal): string
    {
        $value = trim($description);
        if ($value === '') {
            return '';
        }

        $sentences = $this->splitSentences($value);
        if ($sentences === []) {
            return '';
        }

        $sentences = array_slice($sentences, 0, 3);
        $lower = Str::lower(implode(' ', $sentences));

        if (! $this->containsAny($lower, ['pozorovat', 'sledovat', 'viditel'])) {
            if (count($sentences) < 3) {
                $sentences[] = 'Oplati sa ho sledovat, pretoze pomaha lepsie pochopit dianie na oblohe.';
            } else {
                $sentences[1] = 'Oplati sa ho sledovat, pretoze pomaha lepsie pochopit dianie na oblohe.';
            }
        }

        $lower = Str::lower(implode(' ', $sentences));
        if (! $this->containsAny($lower, ['zaujimav'])) {
            if (count($sentences) < 3) {
                $sentences[] = 'Zaujimavostou je, ze podobne ukazy pomahaju sledovat pohyb telies na oblohe.';
            } else {
                $sentences[2] = 'Zaujimavostou je, ze podobne ukazy pomahaju sledovat pohyb telies na oblohe.';
            }
        }

        $lower = Str::lower(implode(' ', $sentences));
        if (! $this->containsVisibilityCue($lower)) {
            $visibilitySentence = $this->buildVisibilitySentence($startLocal, $endLocal, $maxLocal);
            if (count($sentences) < 3) {
                $sentences[] = $visibilitySentence;
            } else {
                $visibilityFragment = lcfirst(rtrim($visibilitySentence, '.! '));
                $sentences[2] = rtrim($sentences[2], '.! ') . '; ' . $visibilityFragment . '.';
            }
        }

        if (count($sentences) < 2) {
            $sentences[] = 'Pozorovanie pomaha lepsie pochopit dynamiku oblohy.';
        }

        $sentences = array_map([$this, 'ensureSentenceEnding'], array_slice($sentences, 0, 3));
        return $this->sanitizeText(implode(' ', $sentences), 500);
    }

    /**
     * @return array<int,string>
     */
    private function splitSentences(string $text): array
    {
        $parts = preg_split('/(?<=[.!?])\s+/u', trim($text)) ?: [];
        $parts = array_map(static fn (string $item): string => trim($item), $parts);
        return array_values(array_filter($parts, static fn (string $item): bool => $item !== ''));
    }

    private function containsVisibilityCue(string $text): bool
    {
        if (preg_match('/\b\d{4}-\d{2}-\d{2}\b/u', $text) === 1) {
            return true;
        }

        if (preg_match('/\b\d{1,2}:\d{2}\b/u', $text) === 1) {
            return true;
        }

        return $this->containsAny($text, [
            'viditel',
            'vecer',
            'rano',
            'v noci',
            'po zapade',
            'pred svitanim',
            'pozorovatel',
        ]);
    }

    private function passesSafetyGuards(string $title, string $baseDescription, string $candidateDescription): bool
    {
        $inputContext = $title . "\n" . $baseDescription;

        if ($this->introducesUnknownNumericTokens($inputContext, $candidateDescription)) {
            return false;
        }

        if ($this->mentionsUnexpectedCelestialTerms($inputContext, $candidateDescription)) {
            return false;
        }

        return true;
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

        $tokens = [];
        foreach (($matches[0] ?? []) as $token) {
            $value = strtolower(trim((string) $token));
            if ($value === '') {
                continue;
            }

            $value = str_replace(',', '.', $value);
            $value = preg_replace('/\s+/u', '', $value) ?? $value;
            $tokens[] = $value;
        }

        $tokens = array_values(array_unique($tokens));
        sort($tokens);

        return $tokens;
    }

    private function mentionsUnexpectedCelestialTerms(string $inputContext, string $outputContext): bool
    {
        $inputNormalized = Str::of($inputContext)->ascii()->lower()->value();
        $outputNormalized = Str::of($outputContext)->ascii()->lower()->value();

        $allowed = [];
        $used = [];

        foreach (self::CELESTIAL_TERMS as $term) {
            if (str_contains($inputNormalized, $term)) {
                $allowed[$term] = true;
            }

            if (str_contains($outputNormalized, $term)) {
                $used[$term] = true;
            }
        }

        foreach (array_keys($used) as $term) {
            if (! isset($allowed[$term])) {
                return true;
            }
        }

        return false;
    }

    private function buildVisibilitySentence(string $startLocal, string $endLocal, string $maxLocal): string
    {
        foreach ([$maxLocal, $startLocal, $endLocal] as $value) {
            if ($value !== 'n/a') {
                return "Najlepsia viditelnost je okolo {$value} (lokalny cas).";
            }
        }

        return 'Cas viditelnosti zavisi od polohy pozorovatela.';
    }

    private function ensureSentenceEnding(string $sentence): string
    {
        $value = trim($sentence);
        if ($value === '') {
            return '';
        }

        if (preg_match('/[.!?]$/u', $value) === 1) {
            return $value;
        }

        return $value . '.';
    }

    /**
     * @param array<int,string> $needles
     */
    private function containsAny(string $value, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($value, $needle)) {
                return true;
            }
        }

        return false;
    }
}
