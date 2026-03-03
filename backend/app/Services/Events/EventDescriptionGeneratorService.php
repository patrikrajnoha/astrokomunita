<?php

namespace App\Services\Events;

use App\Models\Event;
use App\Services\AI\JsonGuard;
use App\Services\AI\OllamaClient;
use App\Services\AI\OllamaClientException;
use App\Support\Http\SslVerificationPolicy;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class EventDescriptionGeneratorService
{
    private const TELEMETRY_STATUS_SUCCESS = 'success';
    private const TELEMETRY_STATUS_FALLBACK = 'fallback';
    private const TELEMETRY_STATUS_ERROR = 'error';
    private const NUMERIC_TOKEN_PATTERN = '/\b\d{1,4}(?:[.,:]\d{1,4}){0,2}\b/u';
    private const ISO_TIMESTAMP_PATTERN = '/\b\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(?::\d{2})?(?:Z|[+\-]\d{2}:\d{2})\b/u';
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
        private readonly JsonGuard $jsonGuard,
        private readonly EventInsightsCacheService $insightsCache,
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
     * @return array{description:string,short:string,provider:string,insights?:array{why_interesting:string,how_to_observe:string}}
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
     * @return array{description:string,short:string,provider:string,insights?:array{why_interesting:string,how_to_observe:string}}
     */
    private function generateWithOllama(Event $event): array
    {
        if (! $this->isHumanizedPilotEnabled()) {
            return $this->generateWithOllamaLegacy($event);
        }

        return $this->generateWithOllamaHumanized($event);
    }

    /**
     * @return array{description:string,short:string,provider:string,insights?:array{why_interesting:string,how_to_observe:string}}
     */
    private function generateWithOllamaLegacy(Event $event): array
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

        $model = trim((string) config('events.ai.model', config('ai.ollama.model', 'mistral')));
        $startedAt = microtime(true);
        $inputChars = $this->textLength($system) + $this->textLength($prompt);

        $this->applyOllamaJitterIfNeeded();

        try {
            $result = $this->ollamaClient->generate(
                prompt: $prompt,
                system: $system,
                options: [
                    'model' => $model,
                    'temperature' => (float) config('events.ai.temperature', 0.2),
                    'num_predict' => $this->resolveDescriptionMaxTokens(),
                    'timeout' => (int) config('events.ai.timeout', 45),
                    'max_retries' => 2,
                    'retry_backoff_base_ms' => $this->resolveEventRetryBackoffBaseMs(),
                ]
            );
        } catch (OllamaClientException $exception) {
            $this->emitGenerationTelemetry(
                eventId: (int) $event->id,
                model: $model,
                startedAt: $startedAt,
                status: self::TELEMETRY_STATUS_ERROR,
                retryCount: 0,
                inputChars: $inputChars,
                outputChars: 0
            );
            throw new RuntimeException($exception->getMessage(), 0, $exception);
        } catch (Throwable $exception) {
            $this->emitGenerationTelemetry(
                eventId: (int) $event->id,
                model: $model,
                startedAt: $startedAt,
                status: self::TELEMETRY_STATUS_ERROR,
                retryCount: 0,
                inputChars: $inputChars,
                outputChars: 0
            );
            throw new RuntimeException('Event description generation failed.', 0, $exception);
        }

        $raw = (string) ($result['text'] ?? '');
        $retryCount = max(0, (int) ($result['retry_count'] ?? 0));
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

        $this->emitGenerationTelemetry(
            eventId: (int) $event->id,
            model: $model !== '' ? $model : (string) ($result['model'] ?? ''),
            startedAt: $startedAt,
            status: $provider === 'ollama'
                ? self::TELEMETRY_STATUS_SUCCESS
                : self::TELEMETRY_STATUS_FALLBACK,
            retryCount: $retryCount,
            inputChars: $inputChars,
            outputChars: $this->textLength($raw)
        );

        return [
            'description' => $description,
            'short' => $short,
            'provider' => $provider,
        ];
    }

    /**
     * @return array{description:string,short:string,provider:string,insights?:array{why_interesting:string,how_to_observe:string}}
     */
    private function generateWithOllamaHumanized(Event $event): array
    {
        $template = $this->templateBuilder->build($event);
        $factualPack = $this->insightsCache->buildFactualPackForHash($event);
        $factualPackJson = json_encode($factualPack, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (! is_string($factualPackJson) || $factualPackJson === '') {
            $factualPackJson = '{}';
        }

        $system = 'Si redaktor astronomickeho kalendara. Vystupuj iba po slovensky so spravnou diakritikou.';
        $prompt = <<<PROMPT
Zadanie:
Mas fakticky balicek eventu vo formate JSON. Tento balicek je jediny zdroj faktov.
Tvojou ulohou je preformulovat text tak, aby bol ludsky, jasny a uzitocny, ale bez zmeny faktov.

Pravidla:
1. Vrat STRICT JSON objekt bez markdownu a bez dodatocneho textu.
2. JSON musi obsahovat presne kluce:
   - "description"
   - "short"
   - "why_interesting"
   - "how_to_observe"
3. Kazda hodnota musi byt string.
4. Limity dlzky:
   - short: max 180 znakov
   - description: max 500 znakov
   - why_interesting: max 200 znakov
   - how_to_observe: max 250 znakov
5. NIKDY nemen cisla, datumy, casy ani nazvy objektov z factual packu.
6. Ak informacia nie je vo factual packu, nepridavaj ju ako fakt.
7. Mozes pridat iba vseobecne rady na pozorovanie (napr. tmave miesto, adaptacia zraku, stabilna atmosfera), bez konkretnych neoverenych tvrdeni.

Factual pack JSON:
{$factualPackJson}
PROMPT;

        $model = trim((string) config('events.ai.model', config('ai.ollama.model', 'mistral')));
        $startedAt = microtime(true);
        $inputChars = $this->textLength($system) + $this->textLength($prompt);

        $this->applyOllamaJitterIfNeeded();

        try {
            $result = $this->ollamaClient->generate(
                prompt: $prompt,
                system: $system,
                options: [
                    'model' => $model,
                    'temperature' => $this->resolveHumanizedTemperature(),
                    'num_predict' => $this->resolveHumanizedNumPredict(),
                    'timeout' => max(1, (int) config('events.ai.timeout', 45)),
                    'max_retries' => 2,
                    'retry_backoff_base_ms' => $this->resolveEventRetryBackoffBaseMs(),
                ]
            );
        } catch (OllamaClientException $exception) {
            $this->emitGenerationTelemetry(
                eventId: (int) $event->id,
                model: $model,
                startedAt: $startedAt,
                status: self::TELEMETRY_STATUS_ERROR,
                retryCount: 0,
                inputChars: $inputChars,
                outputChars: 0
            );
            throw new RuntimeException($exception->getMessage(), 0, $exception);
        } catch (Throwable $exception) {
            $this->emitGenerationTelemetry(
                eventId: (int) $event->id,
                model: $model,
                startedAt: $startedAt,
                status: self::TELEMETRY_STATUS_ERROR,
                retryCount: 0,
                inputChars: $inputChars,
                outputChars: 0
            );
            throw new RuntimeException('Event description generation failed.', 0, $exception);
        }

        $raw = (string) ($result['text'] ?? '');
        $retryCount = max(0, (int) ($result['retry_count'] ?? 0));
        $guardResult = $this->jsonGuard->parseAndValidate($raw, [
            'description' => 500,
            'short' => 180,
            'why_interesting' => 200,
            'how_to_observe' => 250,
        ]);

        if (! (bool) ($guardResult['valid'] ?? false)) {
            $this->emitGenerationTelemetry(
                eventId: (int) $event->id,
                model: $model !== '' ? $model : (string) ($result['model'] ?? ''),
                startedAt: $startedAt,
                status: self::TELEMETRY_STATUS_FALLBACK,
                retryCount: $retryCount,
                inputChars: $inputChars,
                outputChars: $this->textLength($raw)
            );
            return $this->guardFallback($event, $template, (array) ($guardResult['errors'] ?? []));
        }

        $payload = (array) ($guardResult['data'] ?? []);
        $description = $this->sanitizeText((string) ($payload['description'] ?? ''), 500);
        $short = $this->sanitizeText((string) ($payload['short'] ?? ''), 180);
        $whyInteresting = $this->sanitizeText((string) ($payload['why_interesting'] ?? ''), 200);
        $howToObserve = $this->sanitizeText((string) ($payload['how_to_observe'] ?? ''), 250);

        if ($description === '' && $whyInteresting !== '') {
            $description = $whyInteresting;
        }
        if ($description === '' && $howToObserve !== '') {
            $description = $howToObserve;
        }

        $provider = 'ollama_humanized';
        if ($description === '') {
            $description = (string) ($template['description'] ?? '');
            $provider = 'template';
        }

        if ($short === '') {
            $short = Str::limit($description, 180, '');
        }

        $insights = [
            'why_interesting' => $whyInteresting,
            'how_to_observe' => $howToObserve,
        ];
        $driftErrors = $this->detectHumanizedFactualDrift($description, $short, $insights, $factualPack);
        if ($driftErrors !== []) {
            $this->emitGenerationTelemetry(
                eventId: (int) $event->id,
                model: $model !== '' ? $model : (string) ($result['model'] ?? ''),
                startedAt: $startedAt,
                status: self::TELEMETRY_STATUS_FALLBACK,
                retryCount: $retryCount,
                inputChars: $inputChars,
                outputChars: $this->textLength($raw)
            );

            return $this->guardFallback($event, $template, $driftErrors);
        }

        $this->emitGenerationTelemetry(
            eventId: (int) $event->id,
            model: $model !== '' ? $model : (string) ($result['model'] ?? ''),
            startedAt: $startedAt,
            status: $provider === 'ollama_humanized'
                ? self::TELEMETRY_STATUS_SUCCESS
                : self::TELEMETRY_STATUS_FALLBACK,
            retryCount: $retryCount,
            inputChars: $inputChars,
            outputChars: $this->textLength($raw)
        );

        // TODO(newsletter/admin-preview): consume insights for newsletter tips or admin AI preview
        // once UI/storage pipeline is ready. We intentionally do not persist these fields yet.
        return [
            'description' => $description,
            'short' => $short,
            'provider' => $provider,
            'insights' => $insights,
        ];
    }

    /**
     * @param array{description:string,short:string,provider:string} $template
     * @param array<int,string> $errors
     * @return array{description:string,short:string,provider:string}
     */
    private function guardFallback(Event $event, array $template, array $errors): array
    {
        Log::warning('Event description generation JSON guard fallback.', [
            'event_id' => (int) $event->id,
            'error_codes' => array_values(array_unique($errors)),
        ]);

        $description = $this->sanitizeText((string) ($template['description'] ?? ''), 500);
        $short = $this->sanitizeText((string) ($template['short'] ?? ''), 180);
        if ($short === '') {
            $short = Str::limit($description, 180, '');
        }

        return [
            'description' => $description,
            'short' => $short,
            'provider' => 'template_guard_fallback',
        ];
    }

    /**
     * @param array{why_interesting:string,how_to_observe:string} $insights
     * @param array<string,mixed> $factualPack
     * @return array<int,string>
     */
    private function detectHumanizedFactualDrift(
        string $description,
        string $short,
        array $insights,
        array $factualPack
    ): array {
        $errors = [];
        $text = trim(implode("\n", array_filter([
            $description,
            $short,
            (string) ($insights['why_interesting'] ?? ''),
            (string) ($insights['how_to_observe'] ?? ''),
        ], static fn (string $value): bool => $value !== '')));

        if ($text === '') {
            return ['factual_drift:empty_output'];
        }

        if (preg_match(self::ISO_TIMESTAMP_PATTERN, $text) === 1) {
            $errors[] = 'factual_drift:iso_timestamp';
        }

        $keyValues = is_array($factualPack['key_values'] ?? null)
            ? (array) $factualPack['key_values']
            : [];

        $expectedDistanceKm = $keyValues['distance_km'] ?? null;
        if (is_numeric($expectedDistanceKm) && $this->hasDistanceKmDrift($text, (float) $expectedDistanceKm)) {
            $errors[] = 'factual_drift:distance_km';
        }

        $expectedSeparationDeg = $keyValues['separation_deg'] ?? null;
        if (is_numeric($expectedSeparationDeg) && $this->hasSeparationDegDrift($text, (float) $expectedSeparationDeg)) {
            $errors[] = 'factual_drift:separation_deg';
        }

        return array_values(array_unique($errors));
    }

    private function hasDistanceKmDrift(string $text, float $expectedDistanceKm): bool
    {
        preg_match_all('/([0-9][0-9\s.,]*)\s*km\b/iu', $text, $matches);
        $tokens = $matches[1] ?? [];
        if (! is_array($tokens) || $tokens === []) {
            return false;
        }

        foreach ($tokens as $token) {
            $candidate = $this->parseLooseNumber((string) $token);
            if ($candidate === null) {
                continue;
            }

            if (abs($candidate - $expectedDistanceKm) > 0.5) {
                return true;
            }
        }

        return false;
    }

    private function hasSeparationDegDrift(string $text, float $expectedSeparationDeg): bool
    {
        preg_match_all('/([0-9]+(?:[.,][0-9]+)?)\s*(?:\x{00B0}|deg|stupn(?:e|ov)?)\b/iu', $text, $matches);
        $tokens = $matches[1] ?? [];
        if (! is_array($tokens) || $tokens === []) {
            return false;
        }

        foreach ($tokens as $token) {
            $candidate = $this->parseLooseNumber((string) $token);
            if ($candidate === null) {
                continue;
            }

            if (abs($candidate - $expectedSeparationDeg) > 0.05) {
                return true;
            }
        }

        return false;
    }

    private function parseLooseNumber(string $value): ?float
    {
        $normalized = preg_replace('/\s+/u', '', trim($value)) ?? trim($value);
        $normalized = str_replace(',', '.', $normalized);
        if ($normalized === '' || ! is_numeric($normalized)) {
            return null;
        }

        return (float) $normalized;
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

    private function resolveDescriptionMaxTokens(): int
    {
        $configured = (int) config('ai.ollama_max_tokens_description', config('events.ai.num_predict', 420));
        return max(350, min(450, $configured));
    }

    private function isHumanizedPilotEnabled(): bool
    {
        return (bool) config('events.ai.humanized_pilot_enabled', false);
    }

    private function resolveHumanizedTemperature(): float
    {
        $value = (float) config('events.ai.humanized_temperature', config('events.ai.temperature', 0.3));
        return max(0.2, min(0.4, $value));
    }

    private function resolveHumanizedNumPredict(): int
    {
        $value = (int) config('events.ai.humanized_num_predict', config('events.ai.num_predict', 420));
        return max(320, min(700, $value));
    }

    private function resolveEventRetryBackoffBaseMs(): int
    {
        $value = (int) config('events.ai.retry_backoff_base_ms', config('ai.ollama.retry_backoff_base_ms', 250));
        return max(50, $value);
    }

    private function applyOllamaJitterIfNeeded(): void
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

    private function emitGenerationTelemetry(
        int $eventId,
        string $model,
        float $startedAt,
        string $status,
        int $retryCount,
        int $inputChars,
        int $outputChars
    ): void {
        if (! in_array($status, [
            self::TELEMETRY_STATUS_SUCCESS,
            self::TELEMETRY_STATUS_FALLBACK,
            self::TELEMETRY_STATUS_ERROR,
        ], true)) {
            $status = self::TELEMETRY_STATUS_ERROR;
        }

        Log::info('AI feature telemetry', [
            'feature_name' => 'event_description_generate',
            'event_id' => $eventId,
            'model' => $model !== '' ? $model : $this->resolveOllamaModel(),
            'latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'status' => $status,
            'retry_count' => max(0, $retryCount),
            'input_chars' => max(0, $inputChars),
            'output_chars' => max(0, $outputChars),
        ]);
    }

    private function textLength(string $value): int
    {
        return function_exists('mb_strlen')
            ? mb_strlen($value, 'UTF-8')
            : strlen($value);
    }
}
