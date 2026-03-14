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
    private const MAX_TAG_NAME_LENGTH = 40;
    private const MAX_REASON_LENGTH = 120;
    private const MAX_TITLE_LENGTH = 180;
    private const MAX_CONTENT_LENGTH = 4000;
    public const MODE_EXISTING_ONLY = 'existing_only';
    public const MODE_ALLOW_NEW = 'allow_new';

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
     *   reason:?string,
     *   last_run:array<string,mixed>
     * }
     */
    public function suggestForPost(BlogPost $blogPost, string $mode = self::MODE_EXISTING_ONLY): array
    {
        $startedAt = microtime(true);
        $entityId = (int) $blogPost->id;
        $normalizedMode = $this->normalizeSuggestionMode($mode);
        $allowNewTags = $normalizedMode === self::MODE_ALLOW_NEW;

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

        if ($title === '' || $content === '') {
            return $this->finalize(
                status: 'fallback',
                tags: [],
                fallbackUsed: true,
                entityId: $entityId,
                latencyMs: (int) round((microtime(true) - $startedAt) * 1000),
                retryCount: 0,
                reason: 'missing_title_or_content'
            );
        }

        if ($allowNewTags) {
            return $this->suggestAllowingNewTags(
                blogPost: $blogPost,
                entityId: $entityId,
                startedAt: $startedAt,
                title: $title,
                content: $content,
                existingTags: $existingTags
            );
        }

        if ($existingTags === []) {
            return $this->finalize(
                status: 'fallback',
                tags: [],
                fallbackUsed: true,
                entityId: $entityId,
                latencyMs: (int) round((microtime(true) - $startedAt) * 1000),
                retryCount: 0,
                reason: 'no_existing_tags'
            );
        }

        $existingTagsByName = $this->buildExistingTagsByName($existingTags);

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
        $fallbackTags = $this->buildDeterministicFallbackTags(
            title: $title,
            content: $content,
            existingTags: $existingTags,
            alreadyAttachedTagIds: $alreadyAttachedTagIds
        );

        try {
            $response = $this->ollamaClient->generate(
                prompt: $prompt,
                system: $this->systemPromptForExistingTags(),
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
                status: $fallbackTags === [] ? 'error' : 'fallback',
                tags: $fallbackTags,
                fallbackUsed: true,
                entityId: $entityId,
                latencyMs: (int) round((microtime(true) - $startedAt) * 1000),
                retryCount: 0,
                reason: 'provider_error'
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
                tags: $fallbackTags,
                fallbackUsed: true,
                entityId: $entityId,
                latencyMs: $latencyMs,
                retryCount: $retryCount,
                reason: 'invalid_json'
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
                tags: $fallbackTags,
                fallbackUsed: true,
                entityId: $entityId,
                latencyMs: $latencyMs,
                retryCount: $retryCount,
                reason: 'invalid_ai_payload'
            );
        }

        if ($suggestedTags['tags'] === [] && $fallbackTags !== []) {
            return $this->finalize(
                status: 'fallback',
                tags: $fallbackTags,
                fallbackUsed: true,
                entityId: $entityId,
                latencyMs: $latencyMs,
                retryCount: $retryCount,
                reason: 'ai_empty_result'
            );
        }

        return $this->finalize(
            status: 'success',
            tags: $suggestedTags['tags'],
            fallbackUsed: false,
            entityId: $entityId,
            latencyMs: $latencyMs,
            retryCount: $retryCount,
            reason: null
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
     * @param array<string,mixed> $data
     * @param array<string,array{id:int,name:string}> $existingTagsByName
     * @param array<int,int> $alreadyAttachedTagIds
     * @param array<int,string> $alreadyAttachedTagNames
     * @return array{valid:bool,tags:array<int,array{id:int,name:string,reason:string}>}
     */
    private function validateOpenSuggestions(
        array $data,
        array $existingTagsByName = [],
        array $alreadyAttachedTagIds = [],
        array $alreadyAttachedTagNames = []
    ): array
    {
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
        $seenTagNames = [];
        $alreadyAttachedTagNameKeys = [];
        foreach ($alreadyAttachedTagNames as $attachedName) {
            $key = $this->normalizeTagKey((string) $attachedName);
            if ($key !== '') {
                $alreadyAttachedTagNameKeys[$key] = true;
            }
        }

        foreach (array_slice($tagsRaw, 0, self::MAX_TAG_SUGGESTIONS) as $row) {
            if (! is_array($row) || array_is_list($row)) {
                continue;
            }

            $rowKeys = array_keys($row);
            sort($rowKeys);
            if ($rowKeys !== ['name', 'reason']) {
                continue;
            }

            $name = $this->sanitizeText((string) ($row['name'] ?? ''), self::MAX_TAG_NAME_LENGTH);
            $reason = $this->sanitizeText((string) ($row['reason'] ?? ''), self::MAX_REASON_LENGTH);
            if ($name === '' || $reason === '') {
                continue;
            }

            $nameLength = function_exists('mb_strlen')
                ? mb_strlen($name, 'UTF-8')
                : strlen($name);
            if ($nameLength < 2) {
                continue;
            }

            $normalizedName = $this->normalizeTagKey($name);
            if ($normalizedName === '') {
                continue;
            }

            $matchedTag = $this->resolveExistingTagForCandidate($normalizedName, $existingTagsByName);
            if ($matchedTag !== null) {
                $tagId = (int) ($matchedTag['id'] ?? 0);
                if ($tagId <= 0 || in_array($tagId, $alreadyAttachedTagIds, true) || isset($seenTagIds[$tagId])) {
                    continue;
                }

                $seenTagIds[$tagId] = true;
                $result[] = [
                    'id' => $tagId,
                    'name' => (string) ($matchedTag['name'] ?? $name),
                    'reason' => $reason,
                ];
                continue;
            }

            if (isset($alreadyAttachedTagNameKeys[$normalizedName]) || isset($seenTagNames[$normalizedName])) {
                continue;
            }

            $seenTagNames[$normalizedName] = true;
            $result[] = [
                'id' => 0,
                'name' => $name,
                'reason' => $reason,
            ];
        }

        return [
            'valid' => true,
            'tags' => array_values(array_slice($result, 0, self::MAX_TAG_SUGGESTIONS)),
        ];
    }

    /**
     * @param array<string,array{id:int,name:string}> $existingTagsByName
     * @return array{id:int,name:string}|null
     */
    private function resolveExistingTagForCandidate(string $normalizedName, array $existingTagsByName): ?array
    {
        if ($normalizedName === '' || $existingTagsByName === []) {
            return null;
        }

        if (isset($existingTagsByName[$normalizedName])) {
            return $existingTagsByName[$normalizedName];
        }

        $compactCandidate = $this->compactTagKey($normalizedName);
        if ($compactCandidate === '') {
            return null;
        }

        $bestCandidate = null;
        $bestScore = 0;
        $candidateLength = strlen($compactCandidate);

        foreach ($existingTagsByName as $existingKey => $existingTag) {
            $compactExisting = $this->compactTagKey((string) $existingKey);
            if ($compactExisting === '') {
                continue;
            }

            if ($compactExisting === $compactCandidate) {
                return $existingTag;
            }

            $score = 0;
            $existingLength = strlen($compactExisting);
            $lengthDiff = abs($candidateLength - $existingLength);

            if ($candidateLength >= 5 && $existingLength >= 5) {
                if (str_starts_with($compactCandidate, $compactExisting) || str_starts_with($compactExisting, $compactCandidate)) {
                    $score = max($score, 86 - min(16, 4 * $lengthDiff));
                }

                if (str_contains($compactCandidate, $compactExisting) || str_contains($compactExisting, $compactCandidate)) {
                    $score = max($score, 78 - min(20, 4 * $lengthDiff));
                }
            }

            if ($candidateLength >= 4 && $existingLength >= 4 && $candidateLength <= 28 && $existingLength <= 28) {
                $distance = levenshtein($compactCandidate, $compactExisting);
                $allowedDistance = max($candidateLength, $existingLength) >= 10 ? 2 : 1;
                if ($distance <= $allowedDistance) {
                    $score = max($score, 94 - (12 * $distance) - min(20, 3 * $lengthDiff));
                }
            }

            if ($score < 70) {
                continue;
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestCandidate = $existingTag;
                continue;
            }

            if ($score === $bestScore && $bestCandidate !== null) {
                if ((int) ($existingTag['id'] ?? PHP_INT_MAX) < (int) ($bestCandidate['id'] ?? PHP_INT_MAX)) {
                    $bestCandidate = $existingTag;
                }
            }
        }

        return $bestCandidate;
    }

    private function compactTagKey(string $value): string
    {
        return str_replace(' ', '', trim($value));
    }

    /**
     * @param array<int,array{id:int,name:string}> $existingTags
     * @return array<string,array{id:int,name:string}>
     */
    private function buildExistingTagsByName(array $existingTags): array
    {
        $existingTagsByName = [];

        foreach ($existingTags as $row) {
            $normalizedName = $this->normalizeTagKey((string) ($row['name'] ?? ''));
            if ($normalizedName === '') {
                continue;
            }

            $candidate = [
                'id' => (int) ($row['id'] ?? 0),
                'name' => (string) ($row['name'] ?? ''),
            ];

            if ((int) ($candidate['id'] ?? 0) <= 0 || (string) ($candidate['name'] ?? '') === '') {
                continue;
            }

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

        return $existingTagsByName;
    }

    private function normalizeSuggestionMode(string $mode): string
    {
        return $mode === self::MODE_ALLOW_NEW
            ? self::MODE_ALLOW_NEW
            : self::MODE_EXISTING_ONLY;
    }

    /**
     * @param array<int,array{id:int,name:string}> $existingTags
     * @return array{
     *   status:'success'|'fallback'|'error',
     *   tags:array<int,array{id:int,name:string,reason:string}>,
     *   fallback_used:bool,
     *   reason:?string,
     *   last_run:array<string,mixed>
     * }
     */
    private function suggestAllowingNewTags(
        BlogPost $blogPost,
        int $entityId,
        float $startedAt,
        string $title,
        string $content,
        array $existingTags
    ): array {
        $existingTagsByName = $this->buildExistingTagsByName($existingTags);
        $alreadyAttachedTagIds = $blogPost->tags()
            ->pluck('tags.id')
            ->map(static fn ($id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->all();
        $alreadyAttachedTagIds = array_values(array_unique($alreadyAttachedTagIds));
        $alreadyAttachedTagNames = $blogPost->tags
            ->map(static fn (Tag $tag): string => trim((string) $tag->name))
            ->filter()
            ->values()
            ->all();

        $prompt = $existingTags === []
            ? $this->buildOpenPrompt(
                title: $title,
                content: $content
            )
            : $this->buildFlexiblePrompt(
                title: $title,
                content: $content,
                existingTagNames: array_values(array_map(static fn (array $row): string => $row['name'], $existingTags)),
                alreadyAttachedTagNames: $alreadyAttachedTagNames,
            );
        $systemPrompt = $existingTags === []
            ? $this->systemPromptForOpenTags()
            : $this->systemPromptForFlexibleTags();

        $fallbackTags = $this->buildDeterministicFlexibleFallbackTags(
            title: $title,
            content: $content,
            existingTags: $existingTags,
            existingTagsByName: $existingTagsByName,
            alreadyAttachedTagIds: $alreadyAttachedTagIds,
            alreadyAttachedTagNames: $alreadyAttachedTagNames
        );

        try {
            $response = $this->ollamaClient->generate(
                prompt: $prompt,
                system: $systemPrompt,
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
                status: $this->statusForFallback($fallbackTags),
                tags: $fallbackTags,
                fallbackUsed: true,
                entityId: $entityId,
                latencyMs: (int) round((microtime(true) - $startedAt) * 1000),
                retryCount: 0,
                reason: 'provider_error'
            );
        }

        $latencyMs = isset($response['duration_ms'])
            ? max(0, (int) $response['duration_ms'])
            : (int) round((microtime(true) - $startedAt) * 1000);
        $retryCount = max(0, (int) ($response['retry_count'] ?? 0));

        $parsed = $this->jsonGuard->parseJsonObject((string) ($response['text'] ?? ''));
        if (! (bool) ($parsed['valid'] ?? false)) {
            return $this->finalize(
                status: $this->statusForFallback($fallbackTags),
                tags: $fallbackTags,
                fallbackUsed: true,
                entityId: $entityId,
                latencyMs: $latencyMs,
                retryCount: $retryCount,
                reason: 'invalid_json'
            );
        }

        $suggestedTags = $this->validateOpenSuggestions(
            data: (array) ($parsed['data'] ?? []),
            existingTagsByName: $existingTagsByName,
            alreadyAttachedTagIds: $alreadyAttachedTagIds,
            alreadyAttachedTagNames: $alreadyAttachedTagNames
        );

        if (! $suggestedTags['valid']) {
            return $this->finalize(
                status: $this->statusForFallback($fallbackTags),
                tags: $fallbackTags,
                fallbackUsed: true,
                entityId: $entityId,
                latencyMs: $latencyMs,
                retryCount: $retryCount,
                reason: 'invalid_ai_payload'
            );
        }

        if ($suggestedTags['tags'] === []) {
            return $this->finalize(
                status: $this->statusForFallback($fallbackTags),
                tags: $fallbackTags,
                fallbackUsed: true,
                entityId: $entityId,
                latencyMs: $latencyMs,
                retryCount: $retryCount,
                reason: 'ai_empty_result'
            );
        }

        return $this->finalize(
            status: 'success',
            tags: $suggestedTags['tags'],
            fallbackUsed: false,
            entityId: $entityId,
            latencyMs: $latencyMs,
            retryCount: $retryCount,
            reason: null
        );
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

    /**
     * @param array<int,string> $existingTagNames
     * @param array<int,string> $alreadyAttachedTagNames
     */
    private function buildFlexiblePrompt(
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
- Navrhni najrelevantnejsie tagy pre Learn/Blog clanok.
- Mozes vybrat existujuci tag z existing_tags ALEBO navrhnut novy.
- Nenavrhuj uz pripojene tagy z already_attached_tags.
- Maximalne 5 tagov.

Vrat STRICT JSON objekt presne v tvare:
{"tags":[{"name":"...","reason":"..."}]}

Pravidla:
- tags: pole 0 az 5 poloziek
- name: kratky nazov tagu, max 40 znakov
- reason: kratke zdovodnenie, max 120 znakov
- bez markdownu, bez komentarov, bez dalsich klucov

Input JSON:
{$inputJson}
PROMPT;
    }

    private function buildOpenPrompt(
        string $title,
        string $content
    ): string {
        $input = [
            'article' => [
                'title' => $title,
                'content_excerpt' => $content,
            ],
        ];

        $inputJson = json_encode($input, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (! is_string($inputJson) || trim($inputJson) === '') {
            $inputJson = '{}';
        }

        return <<<PROMPT
Uloha:
- Navrhni najrelevantnejsie tagy pre Learn/Blog clanok podla textu.
- Tag ma byt kratky (1 az 3 slova), konkretny, bez znaku #.
- Maximalne 5 tagov.
- Nenavrhuj prilis vseobecne slova ako "clanok" alebo "obsah".

Vrat STRICT JSON objekt presne v tvare:
{"tags":[{"name":"...","reason":"..."}]}

Pravidla:
- tags: pole 0 az 5 poloziek
- name: max 40 znakov
- reason: kratke zdovodnenie, max 120 znakov
- bez markdownu, bez komentarov, bez dalsich klucov

Input JSON:
{$inputJson}
PROMPT;
    }

    private function systemPromptForExistingTags(): string
    {
        return 'Si editor blogu. Vyberas len existujuce tagy, bez vymyslania novych.';
    }

    private function systemPromptForOpenTags(): string
    {
        return 'Si editor blogu. Navrhuj konkretne tagy podla textu clanku.';
    }

    private function systemPromptForFlexibleTags(): string
    {
        return 'Si editor blogu. Preferuj existujuce tagy, no mozes navrhnut aj nove.';
    }

    /**
     * @param array<int,array{id:int,name:string}> $existingTags
     * @param array<int,int> $alreadyAttachedTagIds
     * @return array<int,array{id:int,name:string,reason:string}>
     */
    private function buildDeterministicFallbackTags(
        string $title,
        string $content,
        array $existingTags,
        array $alreadyAttachedTagIds
    ): array {
        $availableTags = array_values(array_filter(
            $existingTags,
            static fn (array $row): bool =>
                (int) ($row['id'] ?? 0) > 0
                && trim((string) ($row['name'] ?? '')) !== ''
                && ! in_array((int) ($row['id'] ?? 0), $alreadyAttachedTagIds, true)
        ));

        if ($availableTags === []) {
            return [];
        }

        $sourceText = $this->normalizeTagKey($title . ' ' . $content);
        $sourceTokens = $this->buildTokenFrequency($sourceText);
        $sourceTokenList = array_keys($sourceTokens);

        $scored = [];

        foreach ($availableTags as $row) {
            $tagId = (int) ($row['id'] ?? 0);
            $tagName = trim((string) ($row['name'] ?? ''));
            if ($tagId <= 0 || $tagName === '') {
                continue;
            }

            $normalizedTagName = $this->normalizeTagKey($tagName);
            $tagTokens = $this->tokenizeForSearch($normalizedTagName);
            $score = 0;
            $matchedToken = '';

            if ($normalizedTagName !== '' && str_contains($sourceText, $normalizedTagName)) {
                $score += 8;
            }

            foreach ($tagTokens as $tagToken) {
                $tokenScore = (int) ($sourceTokens[$tagToken] ?? 0);
                if ($tokenScore > 0) {
                    $score += min(6, 2 * $tokenScore);
                    if ($matchedToken === '') {
                        $matchedToken = $tagToken;
                    }
                    continue;
                }

                if ($this->hasPrefixTokenMatch($tagToken, $sourceTokenList)) {
                    $score += 1;
                    if ($matchedToken === '') {
                        $matchedToken = $tagToken;
                    }
                }
            }

            if ($score <= 0) {
                continue;
            }

            $reason = $matchedToken !== ''
                ? sprintf('Tematicka zhoda so slovom "%s" v clanku.', $matchedToken)
                : 'Tag sa priamo spomina v clanku.';

            $scored[] = [
                'id' => $tagId,
                'name' => $tagName,
                'reason' => $this->sanitizeText($reason, self::MAX_REASON_LENGTH),
                'score' => $score,
            ];
        }

        if ($scored === []) {
            return array_values(array_map(
                fn (array $row): array => [
                    'id' => (int) ($row['id'] ?? 0),
                    'name' => (string) ($row['name'] ?? ''),
                    'reason' => 'Fallback navrh podla dostupnych tagov.',
                ],
                array_slice($availableTags, 0, self::MAX_TAG_SUGGESTIONS)
            ));
        }

        usort($scored, static function (array $a, array $b): int {
            $scoreOrder = (int) ($b['score'] ?? 0) <=> (int) ($a['score'] ?? 0);
            if ($scoreOrder !== 0) {
                return $scoreOrder;
            }

            return (int) ($a['id'] ?? 0) <=> (int) ($b['id'] ?? 0);
        });

        return array_values(array_map(
            static fn (array $row): array => [
                'id' => (int) ($row['id'] ?? 0),
                'name' => (string) ($row['name'] ?? ''),
                'reason' => (string) ($row['reason'] ?? ''),
            ],
            array_slice($scored, 0, self::MAX_TAG_SUGGESTIONS)
        ));
    }

    /**
     * @param array<int,array{id:int,name:string}> $existingTags
     * @param array<string,array{id:int,name:string}> $existingTagsByName
     * @param array<int,int> $alreadyAttachedTagIds
     * @param array<int,string> $alreadyAttachedTagNames
     * @return array<int,array{id:int,name:string,reason:string}>
     */
    private function buildDeterministicFlexibleFallbackTags(
        string $title,
        string $content,
        array $existingTags,
        array $existingTagsByName,
        array $alreadyAttachedTagIds,
        array $alreadyAttachedTagNames
    ): array {
        $result = [];
        $seenIds = [];
        $seenNames = [];

        $existingFallback = $this->buildDeterministicFallbackTags(
            title: $title,
            content: $content,
            existingTags: $existingTags,
            alreadyAttachedTagIds: $alreadyAttachedTagIds
        );

        foreach ($existingFallback as $row) {
            $tagId = (int) ($row['id'] ?? 0);
            $tagName = trim((string) ($row['name'] ?? ''));
            if ($tagId <= 0 || $tagName === '') {
                continue;
            }

            if (isset($seenIds[$tagId])) {
                continue;
            }

            $seenIds[$tagId] = true;
            $seenNames[$this->normalizeTagKey($tagName)] = true;
            $result[] = [
                'id' => $tagId,
                'name' => $tagName,
                'reason' => (string) ($row['reason'] ?? ''),
            ];
        }

        $openFallback = $this->buildDeterministicOpenFallbackTags(
            title: $title,
            content: $content
        );

        $openDataRows = array_values(array_map(
            static fn (array $row): array => [
                'name' => (string) ($row['name'] ?? ''),
                'reason' => (string) ($row['reason'] ?? ''),
            ],
            $openFallback
        ));

        $mappedOpen = $this->validateOpenSuggestions(
            data: ['tags' => $openDataRows],
            existingTagsByName: $existingTagsByName,
            alreadyAttachedTagIds: $alreadyAttachedTagIds,
            alreadyAttachedTagNames: $alreadyAttachedTagNames
        );

        foreach ((array) ($mappedOpen['tags'] ?? []) as $row) {
            $tagId = (int) ($row['id'] ?? 0);
            $tagName = trim((string) ($row['name'] ?? ''));
            $nameKey = $this->normalizeTagKey($tagName);
            if ($tagName === '' || $nameKey === '') {
                continue;
            }

            if ($tagId > 0) {
                if (isset($seenIds[$tagId])) {
                    continue;
                }

                $seenIds[$tagId] = true;
            } else {
                if (isset($seenNames[$nameKey])) {
                    continue;
                }
            }

            $seenNames[$nameKey] = true;
            $result[] = [
                'id' => $tagId,
                'name' => $tagName,
                'reason' => (string) ($row['reason'] ?? ''),
            ];

            if (count($result) >= self::MAX_TAG_SUGGESTIONS) {
                break;
            }
        }

        return array_values(array_slice($result, 0, self::MAX_TAG_SUGGESTIONS));
    }

    /**
     * @return array<int,array{id:int,name:string,reason:string}>
     */
    private function buildDeterministicOpenFallbackTags(
        string $title,
        string $content
    ): array {
        $sourceTokens = $this->tokenizeForSearch($this->normalizeTagKey($title . ' ' . $content));
        if ($sourceTokens === []) {
            return [];
        }

        $titleTokens = $this->tokenizeForSearch($this->normalizeTagKey($title));
        $titleTokenSet = [];
        foreach ($titleTokens as $token) {
            $titleTokenSet[$token] = true;
        }

        $stopWords = $this->openFallbackStopWords();
        $frequency = [];
        foreach ($sourceTokens as $token) {
            if (isset($stopWords[$token]) || is_numeric($token)) {
                continue;
            }

            $frequency[$token] = (int) (($frequency[$token] ?? 0) + 1);
        }

        if ($frequency === []) {
            return [];
        }

        $scored = [];
        foreach ($frequency as $token => $count) {
            $inTitle = isset($titleTokenSet[$token]);
            if ($count < 2 && ! $inTitle) {
                continue;
            }

            $scored[] = [
                'token' => $token,
                'score' => $count + ($inTitle ? 2 : 0),
                'in_title' => $inTitle,
            ];
        }

        if ($scored === []) {
            foreach ($titleTokenSet as $token => $_) {
                if (isset($stopWords[$token])) {
                    continue;
                }

                $scored[] = [
                    'token' => $token,
                    'score' => 1,
                    'in_title' => true,
                ];
            }
        }

        if ($scored === []) {
            return [];
        }

        usort($scored, static function (array $a, array $b): int {
            $scoreOrder = (int) ($b['score'] ?? 0) <=> (int) ($a['score'] ?? 0);
            if ($scoreOrder !== 0) {
                return $scoreOrder;
            }

            return strcmp((string) ($a['token'] ?? ''), (string) ($b['token'] ?? ''));
        });

        $result = [];
        $seenNames = [];

        foreach ($scored as $row) {
            $token = trim((string) ($row['token'] ?? ''));
            if ($token === '') {
                continue;
            }

            $name = $this->sanitizeText($this->formatGeneratedTagName($token), self::MAX_TAG_NAME_LENGTH);
            if ($name === '') {
                continue;
            }

            $nameKey = $this->normalizeTagKey($name);
            if ($nameKey === '' || isset($seenNames[$nameKey])) {
                continue;
            }

            $seenNames[$nameKey] = true;
            $result[] = [
                'id' => 0,
                'name' => $name,
                'reason' => (bool) ($row['in_title'] ?? false)
                    ? 'Silna tematicka zhoda s nadpisom clanku.'
                    : 'Tag je casto spominany v texte.',
            ];

            if (count($result) >= self::MAX_TAG_SUGGESTIONS) {
                break;
            }
        }

        return $result;
    }

    /**
     * @return array<string,bool>
     */
    private function openFallbackStopWords(): array
    {
        $words = [
            'a',
            'aj',
            'ako',
            'ale',
            'ani',
            'asi',
            'bez',
            'blog',
            'bol',
            'bola',
            'bolo',
            'by',
            'clanok',
            'clanku',
            'co',
            'do',
            'for',
            'from',
            'have',
            'has',
            'ich',
            'je',
            'k',
            'ked',
            'ktora',
            'ktore',
            'ktory',
            'learn',
            'ma',
            'maju',
            'na',
            'nad',
            'ne',
            'nie',
            'obsah',
            'od',
            'po',
            'pod',
            'post',
            'pre',
            'pri',
            'sa',
            'si',
            'su',
            'sme',
            'tak',
            'tam',
            'ten',
            'tento',
            'the',
            'this',
            'to',
            'u',
            'v',
            'vo',
            'with',
            'z',
            'za',
            'ze',
        ];

        $map = [];
        foreach ($words as $word) {
            $map[$word] = true;
        }

        return $map;
    }

    private function formatGeneratedTagName(string $token): string
    {
        $normalized = trim($token);
        if ($normalized === '') {
            return '';
        }

        if (function_exists('mb_substr') && function_exists('mb_strtoupper')) {
            $first = mb_substr($normalized, 0, 1, 'UTF-8');
            $rest = mb_substr($normalized, 1, null, 'UTF-8');
            return mb_strtoupper($first, 'UTF-8') . $rest;
        }

        return ucfirst($normalized);
    }

    /**
     * @return array<string,int>
     */
    private function buildTokenFrequency(string $value): array
    {
        $result = [];
        foreach ($this->tokenizeForSearch($value) as $token) {
            $result[$token] = (int) (($result[$token] ?? 0) + 1);
        }

        return $result;
    }

    /**
     * @return array<int,string>
     */
    private function tokenizeForSearch(string $value): array
    {
        $parts = preg_split('/[^a-z0-9]+/u', $value) ?: [];
        $tokens = [];

        foreach ($parts as $part) {
            $token = trim((string) $part);
            if ($token === '') {
                continue;
            }

            $length = function_exists('mb_strlen')
                ? mb_strlen($token, 'UTF-8')
                : strlen($token);
            if ($length < 3) {
                continue;
            }

            $tokens[] = $token;
        }

        return $tokens;
    }

    /**
     * @param array<int,string> $sourceTokenList
     */
    private function hasPrefixTokenMatch(string $token, array $sourceTokenList): bool
    {
        $tokenLength = function_exists('mb_strlen')
            ? mb_strlen($token, 'UTF-8')
            : strlen($token);
        if ($tokenLength < 4) {
            return false;
        }

        foreach ($sourceTokenList as $sourceToken) {
            $sourceLength = function_exists('mb_strlen')
                ? mb_strlen($sourceToken, 'UTF-8')
                : strlen($sourceToken);
            if ($sourceLength < 4) {
                continue;
            }

            if (str_starts_with($sourceToken, $token) || str_starts_with($token, $sourceToken)) {
                return true;
            }
        }

        return false;
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
     * @param array<int,array{id:int,name:string,reason:string}> $fallbackTags
     */
    private function statusForFallback(array $fallbackTags): string
    {
        return $fallbackTags === [] ? 'error' : 'fallback';
    }

    /**
     * @param array<int,array{id:int,name:string,reason:string}> $tags
     * @return array{
     *   status:'success'|'fallback'|'error',
     *   tags:array<int,array{id:int,name:string,reason:string}>,
     *   fallback_used:bool,
     *   reason:?string,
     *   last_run:array<string,mixed>
     * }
     */
    private function finalize(
        string $status,
        array $tags,
        bool $fallbackUsed,
        int $entityId,
        int $latencyMs,
        int $retryCount,
        ?string $reason = null
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
            'reason' => $reason,
            'last_run' => $lastRun,
        ];
    }
}
