<?php

namespace App\Services\Events;

use App\Models\Event;
use App\Services\AI\OllamaClient;
use App\Services\AI\OllamaClientException;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class EventDescriptionGeneratorService
{
    public function __construct(
        private readonly OllamaClient $ollamaClient,
        private readonly EventDescriptionTemplateBuilder $templateBuilder,
    ) {
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
        $tz = (string) config('events.timezone', 'Europe/Bratislava');
        $startLocal = $this->formatDateTime($event->start_at, $tz);
        $endLocal = $this->formatDateTime($event->end_at, $tz);
        $maxLocal = $this->formatDateTime($event->max_at, $tz);

        $system = 'Si redaktor astronomickeho kalendara v slovencine.';
        $prompt = <<<PROMPT
Vytvor JSON s klucmi "description" a "short".
Poziadavky:
- Jazyk: slovencina
- fakticky, bez halucinacii, nemen cisla ani casy
- description: 2-3 vety, max 500 znakov
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

        if ($description === '' && $short === '') {
            $description = $this->sanitizeText($raw, 500);
        }

        if ($description === '') {
            throw new RuntimeException('Event description generation returned empty content.');
        }

        if ($short === '') {
            $short = Str::limit($description, 180, '');
        }

        return [
            'description' => $description,
            'short' => $short,
            'provider' => 'ollama',
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
}
