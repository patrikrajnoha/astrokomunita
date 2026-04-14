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
    private const DEFAULT_CELESTIAL_TERMS = [
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
    private const DEFAULT_FORBIDDEN_SUBSTRINGS = [
        'conductov',
        'kontakt s ludmi na mesiac',
        'kontaktu s ludmi na mesiac',
    ];
    private const HUMANIZED_DESCRIPTION_MAX = 500;
    private const HUMANIZED_SHORT_MAX = 180;
    private const HUMANIZED_WHY_INTERESTING_MAX = 200;
    private const HUMANIZED_HOW_TO_OBSERVE_MAX = 250;
    private const HUMANIZED_DESCRIPTION_HARD_MAX = 1600;
    private const HUMANIZED_SHORT_HARD_MAX = 420;
    private const HUMANIZED_WHY_INTERESTING_HARD_MAX = 900;
    private const HUMANIZED_HOW_TO_OBSERVE_HARD_MAX = 900;
    private const REJECTED_OUTPUT_LOG_MAX_CHARS = 1800;

    public function __construct(
        private readonly OllamaClient $ollamaClient,
        private readonly EventDescriptionTemplateBuilder $templateBuilder,
        private readonly JsonGuard $jsonGuard,
        private readonly EventInsightsCacheService $insightsCache,
        private readonly ?EventAiPolicyService $eventAiPolicyService = null,
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

            $availableModels = $this->extractOllamaModelNames($response->json());
            if ($availableModels !== [] && ! $this->isModelAvailable($model, $availableModels)) {
                return [
                    'ok' => false,
                    'endpoint' => $endpoint,
                    'model' => $model,
                    'message' => sprintf(
                        'Model "%s" is not available (available: %s).',
                        $model,
                        implode(', ', array_slice($availableModels, 0, 8))
                    ),
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
     * @return array{
     *   description:string,
     *   short:string,
     *   provider:string,
     *   insights?:array{why_interesting:string,how_to_observe:string},
     *   diagnostics?:array{
     *     validation_stage:string,
     *     error_codes:array<int,string>,
     *     raw_output_excerpt:string,
     *     raw_output_length:int,
     *     candidate_output:array<string,mixed>|null
     *   }
     * }
     */
    public function generateForEvent(Event $event, ?string $mode = null): array
    {
        $requestedMode = $this->resolveMode($mode);
        $routing = $this->resolveModeForEvent($event, $requestedMode);
        $resolvedMode = $routing['mode'];

        if ($requestedMode === 'ollama' && $resolvedMode === 'template') {
            Log::info('Event description routing selected deterministic template.', [
                'event_id' => (int) $event->id,
                'type' => (string) ($event->type ?? ''),
                'source_name' => (string) ($event->source_name ?? ''),
                'reason' => $routing['reason'],
                'matched' => $routing['matched'],
            ]);
        }

        if ($resolvedMode === 'template') {
            return $this->templateBuilder->build($event);
        }

        return $this->generateWithOllama($event);
    }

    /**
     * @return array{
     *   description:string,
     *   short:string,
     *   provider:string,
     *   insights?:array{why_interesting:string,how_to_observe:string},
     *   diagnostics?:array{
     *     validation_stage:string,
     *     error_codes:array<int,string>,
     *     raw_output_excerpt:string,
     *     raw_output_length:int,
     *     candidate_output:array<string,mixed>|null
     *   }
     * }
     */
    private function generateWithOllama(Event $event): array
    {
        if (! $this->isHumanizedPilotEnabled()) {
            return $this->generateWithOllamaLegacy($event);
        }

        return $this->generateWithOllamaHumanized($event);
    }

    /**
     * @return array{
     *   description:string,
     *   short:string,
     *   provider:string,
     *   insights?:array{why_interesting:string,how_to_observe:string},
     *   diagnostics?:array{
     *     validation_stage:string,
     *     error_codes:array<int,string>,
     *     raw_output_excerpt:string,
     *     raw_output_length:int,
     *     candidate_output:array<string,mixed>|null
     *   }
     * }
     */
    private function generateWithOllamaLegacy(Event $event): array
    {
        $template = $this->templateBuilder->build($event);
        $tz = (string) config('events.timezone', 'Europe/Bratislava');
        $startLocal = $this->formatDateTime($event->start_at, $tz);
        $endLocal = $this->formatDateTime($event->end_at, $tz);
        $maxLocal = $this->formatDateTime($event->max_at, $tz);
        $promptRules = $this->promptRuleBlock($this->legacyPromptRules(), false);

        $system = 'Si skúsený slovenský redaktor astronomického kalendára. Píš prirodzenou slovenčinou so správnou diakritikou. Pracuj iba s faktami zo vstupu a nikdy nič nedomýšľaj.';
        $prompt = <<<PROMPT
Vytvor JSON s klucmi "description" a "short".
Poziadavky:
{$promptRules}

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
            Log::warning('Event description generation safety/style fallback.', [
                'event_id' => (int) $event->id,
                'provider' => 'ollama',
            ]);
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
     * @return array{
     *   description:string,
     *   short:string,
     *   provider:string,
     *   insights?:array{why_interesting:string,how_to_observe:string},
     *   diagnostics?:array{
     *     validation_stage:string,
     *     error_codes:array<int,string>,
     *     raw_output_excerpt:string,
     *     raw_output_length:int,
     *     candidate_output:array<string,mixed>|null
     *   }
     * }
     */
    private function generateWithOllamaHumanized(Event $event): array
    {
        $template = $this->templateBuilder->build($event);
        $factualPack = $this->insightsCache->buildFactualPackForHash($event);
        $factualPackJson = json_encode($factualPack, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (! is_string($factualPackJson) || $factualPackJson === '') {
            $factualPackJson = '{}';
        }
        $promptRules = $this->promptRuleBlock($this->humanizedPromptRules(), true);

        $system = 'Si skúsený slovenský redaktor astronomického kalendára. Vystupuj iba prirodzenou slovenčinou so správnou diakritikou. Používaj iba overiteľné fakty zo vstupu.';
        $prompt = <<<PROMPT
Zadanie:
Mas fakticky balicek eventu vo formate JSON. Tento balicek je jediny zdroj faktov.
Tvojou ulohou je preformulovat text tak, aby bol ludsky, jasny a uzitocny, ale bez zmeny faktov.

Pravidla:
{$promptRules}

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
        $guardResult = $this->validateHumanizedPayload($raw);

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
            return $this->guardFallback(
                event: $event,
                template: $template,
                errors: (array) ($guardResult['errors'] ?? []),
                context: [
                    'stage' => 'json_guard',
                    'raw_output' => $raw,
                    'validation' => [
                        'errors' => (array) ($guardResult['errors'] ?? []),
                        'warnings' => (array) ($guardResult['warnings'] ?? []),
                    ],
                ]
            );
        }

        $payload = (array) ($guardResult['data'] ?? []);
        $description = $this->sanitizeText((string) ($payload['description'] ?? ''), self::HUMANIZED_DESCRIPTION_MAX);
        $short = $this->sanitizeText((string) ($payload['short'] ?? ''), self::HUMANIZED_SHORT_MAX);
        $whyInteresting = $this->sanitizeText((string) ($payload['why_interesting'] ?? ''), self::HUMANIZED_WHY_INTERESTING_MAX);
        $howToObserve = $this->sanitizeText((string) ($payload['how_to_observe'] ?? ''), self::HUMANIZED_HOW_TO_OBSERVE_MAX);

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

            return $this->guardFallback(
                event: $event,
                template: $template,
                errors: $driftErrors,
                context: [
                    'stage' => 'factual_drift',
                    'raw_output' => $raw,
                    'candidate_output' => [
                        'description' => $description,
                        'short' => $short,
                        'why_interesting' => $whyInteresting,
                        'how_to_observe' => $howToObserve,
                    ],
                ]
            );
        }

        $styleErrors = $this->detectHumanizedStyleIssues($description, $short, $insights);
        if ($styleErrors !== []) {
            $this->emitGenerationTelemetry(
                eventId: (int) $event->id,
                model: $model !== '' ? $model : (string) ($result['model'] ?? ''),
                startedAt: $startedAt,
                status: self::TELEMETRY_STATUS_FALLBACK,
                retryCount: $retryCount,
                inputChars: $inputChars,
                outputChars: $this->textLength($raw)
            );

            return $this->guardFallback(
                event: $event,
                template: $template,
                errors: $styleErrors,
                context: [
                    'stage' => 'style_guard',
                    'raw_output' => $raw,
                    'candidate_output' => [
                        'description' => $description,
                        'short' => $short,
                        'why_interesting' => $whyInteresting,
                        'how_to_observe' => $howToObserve,
                    ],
                ]
            );
        }

        $combinedCandidate = trim(implode("\n", array_filter([
            $description,
            $short,
            $whyInteresting,
            $howToObserve,
        ], static fn (string $value): bool => trim($value) !== '')));

        if (! $this->passesSafetyGuards((string) ($event->title ?? ''), (string) ($template['description'] ?? ''), $combinedCandidate)) {
            $this->emitGenerationTelemetry(
                eventId: (int) $event->id,
                model: $model !== '' ? $model : (string) ($result['model'] ?? ''),
                startedAt: $startedAt,
                status: self::TELEMETRY_STATUS_FALLBACK,
                retryCount: $retryCount,
                inputChars: $inputChars,
                outputChars: $this->textLength($raw)
            );

            return $this->guardFallback(
                event: $event,
                template: $template,
                errors: ['factual_drift:unexpected_terms_or_numbers'],
                context: [
                    'stage' => 'safety_guard',
                    'raw_output' => $raw,
                    'candidate_output' => [
                        'description' => $description,
                        'short' => $short,
                        'why_interesting' => $whyInteresting,
                        'how_to_observe' => $howToObserve,
                    ],
                ]
            );
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
     * @return array{
     *   description:string,
     *   short:string,
     *   provider:string,
     *   diagnostics:array{
     *     validation_stage:string,
     *     error_codes:array<int,string>,
     *     raw_output_excerpt:string,
     *     raw_output_length:int,
     *     candidate_output:array<string,mixed>|null
     *   }
     * }
     */
    private function guardFallback(
        Event $event,
        array $template,
        array $errors,
        array $context = []
    ): array
    {
        $description = $this->sanitizeText((string) ($template['description'] ?? ''), 500);
        $short = $this->sanitizeText((string) ($template['short'] ?? ''), 180);
        if ($short === '') {
            $short = Str::limit($description, 180, '');
        }

        $rawOutput = (string) ($context['raw_output'] ?? '');
        $validationStage = (string) ($context['stage'] ?? 'json_guard');
        $errorCodes = array_values(array_unique(array_filter(array_map(
            static fn (mixed $value): string => trim((string) $value),
            $errors
        ), static fn (string $value): bool => $value !== '')));
        $candidateOutput = is_array($context['candidate_output'] ?? null)
            ? $context['candidate_output']
            : null;
        $rawOutputExcerpt = $this->logExcerpt($rawOutput, self::REJECTED_OUTPUT_LOG_MAX_CHARS);
        $rawOutputLength = $this->textLength($rawOutput);

        Log::warning('Event description generation JSON guard fallback.', [
            'event_id' => (int) $event->id,
            'validation_stage' => $validationStage,
            'error_codes' => $errorCodes,
            'validation' => is_array($context['validation'] ?? null) ? $context['validation'] : null,
            'raw_output_length' => $rawOutputLength,
            'raw_output_excerpt' => $rawOutputExcerpt,
            'candidate_output' => $candidateOutput,
            'fallback_output' => [
                'description' => $description,
                'short' => $short,
            ],
        ]);

        return [
            'description' => $description,
            'short' => $short,
            'provider' => 'template_guard_fallback',
            'diagnostics' => [
                'validation_stage' => $validationStage,
                'error_codes' => $errorCodes,
                'raw_output_excerpt' => $this->logExcerpt($rawOutput, 480),
                'raw_output_length' => $rawOutputLength,
                'candidate_output' => $candidateOutput,
            ],
        ];
    }

    /**
     * @return array{valid:bool,data:array{description:string,short:string,why_interesting:string,how_to_observe:string},errors:array<int,string>,warnings:array<int,string>}
     */
    private function validateHumanizedPayload(string $raw): array
    {
        $parsed = $this->jsonGuard->parseJsonObject($raw);
        if (! (bool) ($parsed['valid'] ?? false)) {
            return [
                'valid' => false,
                'data' => [
                    'description' => '',
                    'short' => '',
                    'why_interesting' => '',
                    'how_to_observe' => '',
                ],
                'errors' => array_values(array_unique((array) ($parsed['errors'] ?? []))),
                'warnings' => [],
            ];
        }

        $decoded = (array) ($parsed['data'] ?? []);
        $warnings = [];

        $description = $this->normalizeHumanizedField(
            decoded: $decoded,
            key: 'description',
            hardLimit: self::HUMANIZED_DESCRIPTION_HARD_MAX,
            warnings: $warnings
        );
        $short = $this->normalizeHumanizedField(
            decoded: $decoded,
            key: 'short',
            hardLimit: self::HUMANIZED_SHORT_HARD_MAX,
            warnings: $warnings
        );
        $whyInteresting = $this->normalizeHumanizedField(
            decoded: $decoded,
            key: 'why_interesting',
            hardLimit: self::HUMANIZED_WHY_INTERESTING_HARD_MAX,
            warnings: $warnings
        );
        $howToObserve = $this->normalizeHumanizedField(
            decoded: $decoded,
            key: 'how_to_observe',
            hardLimit: self::HUMANIZED_HOW_TO_OBSERVE_HARD_MAX,
            warnings: $warnings
        );

        $errors = [];
        if ($description === '' && $short === '') {
            $errors[] = 'missing_required_text_fields:description_or_short';
        }

        return [
            'valid' => $errors === [],
            'data' => [
                'description' => $description,
                'short' => $short,
                'why_interesting' => $whyInteresting,
                'how_to_observe' => $howToObserve,
            ],
            'errors' => array_values(array_unique($errors)),
            'warnings' => array_values(array_unique($warnings)),
        ];
    }

    /**
     * @param array<string,mixed> $decoded
     * @param array<int,string> $warnings
     */
    private function normalizeHumanizedField(
        array $decoded,
        string $key,
        int $hardLimit,
        array &$warnings
    ): string {
        if (! array_key_exists($key, $decoded)) {
            return '';
        }

        $value = $decoded[$key];
        if (! is_string($value)) {
            $warnings[] = 'invalid_type:' . $key;
            return '';
        }

        $normalized = $this->normalizeModelText($value);
        if ($normalized === '') {
            return '';
        }

        if ($this->textLength($normalized) > $hardLimit) {
            $warnings[] = 'max_length_hard_exceeded:' . $key;
            return '';
        }

        return $normalized;
    }

    private function normalizeModelText(string $value): string
    {
        $plain = trim(strip_tags($value));
        if ($plain === '') {
            return '';
        }

        $plain = preg_replace('/\s+/u', ' ', $plain) ?? $plain;
        return trim($plain);
    }

    private function logExcerpt(string $value, int $maxLength): string
    {
        $normalized = $this->normalizeModelText($value);
        if ($normalized === '') {
            return '';
        }

        return Str::limit($normalized, max(64, $maxLength), ' ...');
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

    /**
     * @param array{why_interesting:string,how_to_observe:string} $insights
     * @return array<int,string>
     */
    private function detectHumanizedStyleIssues(string $description, string $short, array $insights): array
    {
        $errors = [];
        $fields = [
            'description' => $description,
            'short' => $short,
            'why_interesting' => (string) ($insights['why_interesting'] ?? ''),
            'how_to_observe' => (string) ($insights['how_to_observe'] ?? ''),
        ];

        foreach ($fields as $field => $value) {
            if ($this->containsUnnaturalSlovakArtifacts($value)) {
                $errors[] = 'style_guard:unnatural_slovak:' . $field;
            }
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

    /**
     * Conservative router for event descriptions:
     * - keep AI for richer/high-value narratives
     * - default to deterministic template for simple technical/positional events
     *   where wording quality and factual precision are easier to preserve with templates.
     *
     * @return array{mode:string,reason:string,matched:?string}
     */
    private function resolveModeForEvent(Event $event, string $requestedMode): array
    {
        if ($requestedMode !== 'ollama') {
            return [
                'mode' => $requestedMode,
                'reason' => 'requested_mode',
                'matched' => null,
            ];
        }

        if (! (bool) config('events.ai.description_routing.enabled', true)) {
            return [
                'mode' => $requestedMode,
                'reason' => 'routing_disabled',
                'matched' => null,
            ];
        }

        $eventType = $this->normalizeRoutingToken((string) ($event->type ?? ''));
        $templateRule = $this->matchTemplateByDefaultRule($event, $eventType);
        if ($templateRule !== null) {
            return [
                'mode' => 'template',
                'reason' => $templateRule['reason'],
                'matched' => $templateRule['matched'],
            ];
        }

        if ($eventType !== '' && in_array($eventType, $this->routingList('ai_worthy_types'), true)) {
            return [
                'mode' => 'ollama',
                'reason' => 'ai_worthy_type',
                'matched' => $eventType,
            ];
        }

        return [
            'mode' => 'ollama',
            'reason' => 'default_ollama',
            'matched' => null,
        ];
    }

    /**
     * @return array{reason:string,matched:string}|null
     */
    private function matchTemplateByDefaultRule(Event $event, string $normalizedEventType): ?array
    {
        if ($normalizedEventType !== '' && in_array($normalizedEventType, $this->routingList('template_by_default_types'), true)) {
            return [
                'reason' => 'template_by_type',
                'matched' => $normalizedEventType,
            ];
        }

        $normalizedSource = $this->normalizeRoutingToken((string) ($event->source_name ?? ''));
        if ($normalizedSource !== '' && in_array($normalizedSource, $this->routingList('template_by_default_sources'), true)) {
            return [
                'reason' => 'template_by_source',
                'matched' => $normalizedSource,
            ];
        }

        $title = trim((string) ($event->title ?? ''));
        $normalizedTitle = $this->normalizeRoutingToken($title);
        foreach ($this->routingList('template_by_default_title_keywords') as $keyword) {
            if ($keyword !== '' && str_contains($normalizedTitle, $keyword)) {
                return [
                    'reason' => 'template_by_title_keyword',
                    'matched' => $keyword,
                ];
            }
        }

        if (preg_match('/\b\d+(?:[.,]\d+)?\s*(?:\x{00B0}|deg)\b/u', $title) === 1) {
            if (
                str_contains($normalizedTitle, 'od mesiaca')
                || str_contains($normalizedTitle, 'of moon')
                || str_contains($normalizedTitle, 'from moon')
            ) {
                return [
                    'reason' => 'template_by_angular_distance',
                    'matched' => 'angular_distance_moon',
                ];
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function routingList(string $key): array
    {
        $value = config('events.ai.description_routing.' . $key, []);
        if (! is_array($value)) {
            return [];
        }

        $normalized = [];
        foreach ($value as $item) {
            if (! is_string($item)) {
                continue;
            }

            $token = $this->normalizeRoutingToken($item);
            if ($token !== '') {
                $normalized[] = $token;
            }
        }

        return array_values(array_unique($normalized));
    }

    private function normalizeRoutingToken(string $value): string
    {
        $ascii = Str::of($value)->ascii()->lower()->value();
        $ascii = preg_replace('/\s+/u', ' ', $ascii) ?? $ascii;
        return trim($ascii);
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

        if (
            $this->safetyGuardEnabled('numeric_token_guard_enabled', true)
            && $this->introducesUnknownNumericTokens($inputContext, $candidateDescription)
        ) {
            return false;
        }

        if (
            $this->safetyGuardEnabled('celestial_term_guard_enabled', true)
            && $this->mentionsUnexpectedCelestialTerms($inputContext, $candidateDescription)
        ) {
            return false;
        }

        if (
            $this->safetyGuardEnabled('artifact_guard_enabled', true)
            && $this->containsUnnaturalSlovakArtifacts($candidateDescription)
        ) {
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
        $terms = $this->celestialTermsPolicyList();

        $allowed = [];
        $used = [];

        foreach ($terms as $term) {
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

    private function containsUnnaturalSlovakArtifacts(string $text): bool
    {
        if (trim($text) === '') {
            return false;
        }

        $normalized = Str::of($text)->ascii()->lower()->value();
        $forbidden = $this->forbiddenSubstringPolicyList();
        foreach ($forbidden as $needle) {
            if ($needle !== '' && str_contains($normalized, $needle)) {
                return true;
            }
        }

        $regexList = $this->forbiddenRegexPolicyList();
        if ($regexList !== [] && $this->matchesAnyForbiddenRegex($text, $regexList)) {
            return true;
        }

        return false;
    }

    /**
     * @return array<int,string>
     */
    private function forbiddenRegexPolicyList(): array
    {
        $items = $this->policyList('safety.forbidden_regex', []);
        $normalized = [];

        foreach ($items as $pattern) {
            $trimmed = trim((string) $pattern);
            if ($trimmed === '') {
                continue;
            }

            if (! EventAiPolicyService::isRegexPatternValid($trimmed)) {
                continue;
            }

            $normalized[] = $trimmed;
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @param array<int,string> $patterns
     */
    private function matchesAnyForbiddenRegex(string $text, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            $normalized = EventAiPolicyService::normalizeRegexPattern($pattern);
            if ($normalized === null) {
                continue;
            }

            $match = @preg_match($normalized, $text);
            if ($match === 1) {
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

    /**
     * @return array<int,string>
     */
    private function legacyPromptRules(): array
    {
        return $this->policyList(
            'prompts.legacy.rules',
            [
                'Jazyk: slovenčina so správnou diakritikou.',
                'Pis prirodzene ako clovek, nie roboticky ani prehnane formalne.',
                'Neprekladaj text slovo po slove z anglictiny.',
                'Nepouzivaj cudzo znejuce alebo umele slova (napr. "conductovat").',
                'Pouzivaj jednoduche, plynule a bezne slovenske formulacie.',
                'Prepis a obohat BASE_DESCRIPTION, ale nemen jeho fakticky obsah.',
                'Bez halucinacii, nemen cisla ani casy.',
                'Pracuj iba s faktami zo vstupu (title/type/casy/BASE_DESCRIPTION/BASE_SHORT).',
                'Ak fakt vo vstupe chyba alebo je nejasny, pouzi neutralnu formulaciu a nedoplnaj ho odhadom.',
                'Nikdy nespajaj objekty iba podla podobnosti nazvu (napr. Ursids nie je planeta Uranus).',
                'Vyhni sa prehnane sebavedomym tvrdeniam, ak nie su explicitne vo vstupe.',
                'Description ma mat 2-3 plne vety, max 500 znakov.',
                'Veta 1: vysvetli, o aky jav ide.',
                'Veta 2: preco sa oplati jav sledovat a ako ho pozorovat.',
                'Veta 3 (volitelna): kratka zaujimavost pre bezneho pozorovatela.',
                'Description musi obsahovat aj informaciu, kedy je jav viditelny; pouzi iba casy/datumy zo vstupu.',
                'Ak cas vo vstupe chyba, napis neutralne: "Cas viditelnosti zavisi od polohy pozorovatela."',
                'Short ma byt jedna veta, max 180 znakov.',
                'Bez markdownu.',
            ]
        );
    }

    /**
     * @return array<int,string>
     */
    private function humanizedPromptRules(): array
    {
        return $this->policyList(
            'prompts.humanized.rules',
            [
                'Vrat STRICT JSON objekt bez markdownu a bez dodatocneho textu.',
                'JSON musi obsahovat presne kluce: "description", "short", "why_interesting", "how_to_observe".',
                'Kazda hodnota musi byt string.',
                'Limity dlzky: short max 180, description max 500, why_interesting max 200, how_to_observe max 250 znakov.',
                'NIKDY nemen cisla, datumy, casy ani nazvy objektov z factual packu.',
                'Ak informacia nie je vo factual packu, nepridavaj ju ako fakt.',
                'Jazyk musi byt prirodzena plynula slovencina (bez doslovneho prekladu a umelych slov).',
                'Mozes pridat iba vseobecne rady na pozorovanie bez konkretnych neoverenych tvrdeni.',
                'Ak je informacia nejasna alebo chyba, pouzi neutralne formulacie a neuvadzaj odhady.',
                'Nepouzivaj prehnane sebavedomy ton, ak tvrdenie nie je priamo vo factual packu.',
                'Nikdy nespajaj objekty iba podla podobnosti nazvu (napr. Ursids nie je planeta Uranus).',
            ]
        );
    }

    private function promptRuleBlock(array $rules, bool $numbered): string
    {
        $lines = [];
        $index = 1;

        foreach ($rules as $rule) {
            $normalized = trim((string) $rule);
            if ($normalized === '') {
                continue;
            }

            if ($numbered) {
                $lines[] = $index . '. ' . $normalized;
                $index++;
            } else {
                $lines[] = '- ' . $normalized;
            }
        }

        return implode("\n", $lines);
    }

    private function safetyGuardEnabled(string $key, bool $default): bool
    {
        return (bool) $this->policyService()->value('safety.' . $key, $default);
    }

    /**
     * @return array<int,string>
     */
    private function celestialTermsPolicyList(): array
    {
        $items = $this->policyList(
            'safety.celestial_terms',
            self::DEFAULT_CELESTIAL_TERMS
        );

        $normalized = array_values(array_unique(array_filter(array_map(
            fn (string $term): string => $this->normalizeRoutingToken($term),
            $items
        ), static fn (string $term): bool => $term !== '')));

        return $normalized !== [] ? $normalized : self::DEFAULT_CELESTIAL_TERMS;
    }

    /**
     * @return array<int,string>
     */
    private function forbiddenSubstringPolicyList(): array
    {
        $items = $this->policyList(
            'safety.forbidden_substrings',
            self::DEFAULT_FORBIDDEN_SUBSTRINGS
        );

        $normalized = array_values(array_unique(array_filter(array_map(
            fn (string $term): string => $this->normalizeRoutingToken($term),
            $items
        ), static fn (string $term): bool => $term !== '')));

        return $normalized !== [] ? $normalized : self::DEFAULT_FORBIDDEN_SUBSTRINGS;
    }

    /**
     * @param array<int,string> $fallback
     * @return array<int,string>
     */
    private function policyList(string $key, array $fallback): array
    {
        $value = $this->policyService()->value($key, $fallback);
        if (! is_array($value)) {
            $value = $fallback;
        }

        $normalized = array_values(array_filter(array_map(
            static fn (mixed $item): string => is_string($item) ? trim($item) : '',
            $value
        ), static fn (string $item): bool => $item !== ''));

        return $normalized !== [] ? $normalized : $fallback;
    }

    private function policyService(): EventAiPolicyService
    {
        return $this->eventAiPolicyService ?? app(EventAiPolicyService::class);
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

    /**
     * @param mixed $payload
     * @return list<string>
     */
    private function extractOllamaModelNames(mixed $payload): array
    {
        if (! is_array($payload)) {
            return [];
        }

        $models = $payload['models'] ?? null;
        if (! is_array($models)) {
            return [];
        }

        $names = [];
        foreach ($models as $model) {
            if (is_string($model)) {
                $name = trim($model);
            } elseif (is_array($model)) {
                $name = trim((string) ($model['name'] ?? $model['model'] ?? ''));
            } else {
                $name = '';
            }

            if ($name !== '') {
                $names[] = $name;
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * @param list<string> $availableModels
     */
    private function isModelAvailable(string $configuredModel, array $availableModels): bool
    {
        $target = strtolower(trim($configuredModel));
        if ($target === '') {
            return true;
        }

        foreach ($availableModels as $available) {
            $candidate = strtolower(trim($available));
            if ($candidate === '') {
                continue;
            }

            if ($candidate === $target) {
                return true;
            }

            if (str_starts_with($candidate, $target . ':')) {
                return true;
            }

            $targetBase = explode(':', $target, 2)[0];
            $candidateBase = explode(':', $candidate, 2)[0];
            if ($targetBase !== '' && $targetBase === $candidateBase) {
                return true;
            }
        }

        return false;
    }
}
