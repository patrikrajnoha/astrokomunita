<?php

namespace App\Services;

use App\Models\BlogPost;
use App\Models\Tag;
use App\Services\Admin\AiLastRunStore;
use App\Services\AI\JsonGuard;
use App\Services\AI\OllamaClient;
use Throwable;

class BlogTagSuggestionService
{
    private const FEATURE_NAME = 'blog_tag_suggestions';
    private const MAX_TAG_SUGGESTIONS = 5;
    private const MAX_REASON_LENGTH = 120;
    private const MAX_TITLE_LENGTH = 180;
    private const MAX_CONTENT_LENGTH = 4000;

    public function __construct(
        private readonly OllamaClient $ollamaClient,
        private readonly JsonGuard $jsonGuard,
        private readonly AiLastRunStore $lastRunStore,
    ) {
    }

    /**
     * @return array{
     *   status:'success'|'fallback'|'error',
     *   tags:array<int,array{id:int,name:string,reason:string}>,
     *   fallback_used:bool,
     *   last_run:array<string,mixed>
     * }
     */
    public function suggestForPost(BlogPost $blogPost): array
    {
        $startedAt = microtime(true);
        $entityId = (int) $blogPost->id;

        $title = $this->sanitizeText((string) ($blogPost->title ?? ''), self::MAX_TITLE_LENGTH);
        $content = $this->sanitizeText((string) ($blogPost->content ?? ''), self::MAX_CONTENT_LENGTH);

        $existingTags = Tag::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(static fn (Tag $tag): array => [
                'id' => (int) $tag->id,
                'name' => trim((string) $tag->name),
            ])
            ->filter(static fn (array $row): bool => $row['id'] > 0 && $row['name'] !== '')
            ->values()
            ->all();

        if ($existingTags === [] || $title === '' || $content === '') {
            return $this->finalize(
                status: 'fallback',
                tags: [],
                fallbackUsed: true,
                entityId: $entityId,
                latencyMs: (int) round((microtime(true) - $startedAt) * 1000),
                retryCount: 0
            );
        }

        $existingTagsByName = [];
        foreach ($existingTags as $row) {
            $normalizedName = $this->normalizeTagKey($row['name']);
            if ($normalizedName === '') {
                continue;
            }

            $candidate = [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
            ];

            // Deterministic collision rule: if two DB tags normalize to the same key,
            // keep the one with the lowest ID so mapping is stable across runs.
            if (isset($existingTagsByName[$normalizedName])) {
                $current = $existingTagsByName[$normalizedName];
                if ((int) $candidate['id'] >= (int) ($current['id'] ?? PHP_INT_MAX)) {
                    continue;
                }
            }

            $existingTagsByName[$normalizedName] = $candidate;
        }

        $alreadyAttachedTagIds = $blogPost->tags()
            ->pluck('tags.id')
            ->map(static fn ($id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->all();
        $alreadyAttachedTagIds = array_values(array_unique($alreadyAttachedTagIds));

        $prompt = $this->buildPrompt(
            title: $title,
            content: $content,
            existingTagNames: array_values(array_map(static fn (array $row): string => $row['name'], $existingTags)),
            alreadyAttachedTagNames: $blogPost->tags
                ->map(static fn (Tag $tag): string => trim((string) $tag->name))
                ->filter()
                ->values()
                ->all(),
        );

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
                tags: [],
                fallbackUsed: true,
                entityId: $entityId,
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
                tags: [],
                fallbackUsed: true,
                entityId: $entityId,
                latencyMs: $latencyMs,
                retryCount: $retryCount
            );
        }

        $suggestedTags = $this->validateAndMapSuggestions(
            data: (array) ($parsed['data'] ?? []),
            existingTagsByName: $existingTagsByName,
            alreadyAttachedTagIds: $alreadyAttachedTagIds
        );

        if (! $suggestedTags['valid']) {
            return $this->finalize(
                status: 'fallback',
                tags: [],
                fallbackUsed: true,
                entityId: $entityId,
                latencyMs: $latencyMs,
                retryCount: $retryCount
            );
        }

        return $this->finalize(
            status: 'success',
            tags: $suggestedTags['tags'],
            fallbackUsed: false,
            entityId: $entityId,
            latencyMs: $latencyMs,
            retryCount: $retryCount
        );
    }

    /**
     * @param array<string,mixed> $data
     * @param array<string,array{id:int,name:string}> $existingTagsByName
     * @param array<int,int> $alreadyAttachedTagIds
     * @return array{valid:bool,tags:array<int,array{id:int,name:string,reason:string}>}
     */
    private function validateAndMapSuggestions(
        array $data,
        array $existingTagsByName,
        array $alreadyAttachedTagIds
    ): array {
        $keys = array_keys($data);
        sort($keys);
        if ($keys !== ['tags']) {
            return [
                'valid' => false,
                'tags' => [],
            ];
        }

        $tagsRaw = $data['tags'] ?? null;
        if (! is_array($tagsRaw)) {
            return [
                'valid' => false,
                'tags' => [],
            ];
        }

        $result = [];
        $seenTagIds = [];

        foreach (array_slice($tagsRaw, 0, self::MAX_TAG_SUGGESTIONS) as $row) {
            if (! is_array($row) || array_is_list($row)) {
                continue;
            }

            $rowKeys = array_keys($row);
            sort($rowKeys);
            if ($rowKeys !== ['name', 'reason']) {
                continue;
            }

            $name = $this->sanitizeText((string) ($row['name'] ?? ''), 80);
            $reason = $this->sanitizeText((string) ($row['reason'] ?? ''), self::MAX_REASON_LENGTH);
            if ($name === '' || $reason === '') {
                continue;
            }

            $normalizedName = $this->normalizeTagKey($name);
            if ($normalizedName === '' || ! isset($existingTagsByName[$normalizedName])) {
                continue;
            }

            $tag = $existingTagsByName[$normalizedName];
            $tagId = (int) ($tag['id'] ?? 0);
            if ($tagId <= 0) {
                continue;
            }

            if (in_array($tagId, $alreadyAttachedTagIds, true)) {
                continue;
            }

            if (isset($seenTagIds[$tagId])) {
                continue;
            }

            $seenTagIds[$tagId] = true;
            $result[] = [
                'id' => $tagId,
                'name' => (string) ($tag['name'] ?? $name),
                'reason' => $reason,
            ];
        }

        return [
            'valid' => true,
            'tags' => array_values(array_slice($result, 0, self::MAX_TAG_SUGGESTIONS)),
        ];
    }

    /**
     * @param array<int,string> $existingTagNames
     * @param array<int,string> $alreadyAttachedTagNames
     */
    private function buildPrompt(
        string $title,
        string $content,
        array $existingTagNames,
        array $alreadyAttachedTagNames
    ): string {
        $input = [
            'article' => [
                'title' => $title,
                'content_excerpt' => $content,
            ],
            'existing_tags' => array_values(array_unique(array_filter(array_map(
                fn (string $name): string => $this->sanitizeText($name, 80),
                $existingTagNames
            )))),
            'already_attached_tags' => array_values(array_unique(array_filter(array_map(
                fn (string $name): string => $this->sanitizeText($name, 80),
                $alreadyAttachedTagNames
            )))),
        ];

        $inputJson = json_encode($input, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (! is_string($inputJson) || trim($inputJson) === '') {
            $inputJson = '{}';
        }

        return <<<PROMPT
Uloha:
- Vyber najrelevantnejsie EXISTUJUCE tagy pre Learn/Blog clanok.
- Pouzi iba nazvy z pola existing_tags.
- Nenavrhuj uz pripojene tagy z already_attached_tags.
- Maximalne 5 tagov.

Vrat STRICT JSON objekt presne v tvare:
{"tags":[{"name":"...","reason":"..."}]}

Pravidla:
- tags: pole 0 az 5 poloziek
- name: presna zhoda s nazvom z existing_tags
- reason: kratke zdovodnenie, max 120 znakov
- bez markdownu, bez komentarov, bez dalsich klucov

Input JSON:
{$inputJson}
PROMPT;
    }

    private function systemPrompt(): string
    {
        return 'Si editor blogu. Vyberas len existujuce tagy, bez vymyslania novych.';
    }

    private function normalizeTagKey(string $name): string
    {
        $trimmed = trim($name);
        if ($trimmed === '') {
            return '';
        }

        $collapsed = preg_replace('/\s+/u', ' ', $trimmed) ?? $trimmed;
        $withoutDiacritics = $this->removeDiacritics($collapsed);

        return function_exists('mb_strtolower')
            ? mb_strtolower($withoutDiacritics, 'UTF-8')
            : strtolower($withoutDiacritics);
    }

    private function removeDiacritics(string $value): string
    {
        $normalizedBase = $value;

        if (class_exists(\Normalizer::class)) {
            try {
                $normalized = \Normalizer::normalize($value, \Normalizer::FORM_D);
            } catch (Throwable) {
                $normalized = false;
            }

            if (is_string($normalized) && $normalized !== '') {
                $stripped = preg_replace('/\p{Mn}+/u', '', $normalized);
                if (is_string($stripped) && $stripped !== '') {
                    return $stripped;
                }
            }
        }

        if (function_exists('iconv')) {
            $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            if (is_string($transliterated) && $transliterated !== '') {
                return $transliterated;
            }
        }

        return $normalizedBase;
    }

    private function sanitizeText(string $value, int $maxLength): string
    {
        $plain = trim(strip_tags($value));
        if ($plain === '') {
            return '';
        }

        $plain = preg_replace('/\s+/u', ' ', $plain) ?? $plain;
        return $this->truncateUtf8(trim($plain), max(1, $maxLength));
    }

    private function truncateUtf8(string $value, int $maxLength): string
    {
        if ($maxLength <= 0 || $value === '') {
            return '';
        }

        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $maxLength, 'UTF-8');
        }

        return substr($value, 0, $maxLength);
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
     * @param array<int,array{id:int,name:string,reason:string}> $tags
     * @return array{
     *   status:'success'|'fallback'|'error',
     *   tags:array<int,array{id:int,name:string,reason:string}>,
     *   fallback_used:bool,
     *   last_run:array<string,mixed>
     * }
     */
    private function finalize(
        string $status,
        array $tags,
        bool $fallbackUsed,
        int $entityId,
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
            entityId: $entityId,
            retryCount: max(0, $retryCount)
        );

        return [
            'status' => $normalizedStatus,
            'tags' => array_values(array_slice($tags, 0, self::MAX_TAG_SUGGESTIONS)),
            'fallback_used' => $fallbackUsed,
            'last_run' => $lastRun,
        ];
    }
}
