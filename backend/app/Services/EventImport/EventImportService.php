<?php

namespace App\Services\EventImport;

use App\Jobs\TranslateEventCandidateJob;
use App\Models\EventCandidate;
use App\Services\Crawlers\CandidateItem;
use App\Services\EventImport\Parsers\EventSourceParser;
use App\Services\Events\CanonicalKeyService;
use App\Support\EventTime;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class EventImportService
{
    public function __construct(
        private HtmlSourceFetcher $fetcher,
        private EventTypeClassifier $typeClassifier,
        private CanonicalKeyService $canonicalKeyService,
    ) {}

    public function importFromUrl(string $sourceName, string $sourceUrl, EventSourceParser $parser): EventImportResult
    {
        $payload = $this->fetcher->fetch($sourceUrl);
        $items = $parser->parse($payload);

        $candidateItems = array_map(function (EventCandidateData $item) use ($sourceUrl, $payload) {
            return new CandidateItem(
                title: $item->title,
                startsAtUtc: $item->startAt ? $item->startAt->toImmutable()->utc() : now('UTC')->toImmutable(),
                endsAtUtc: $item->endAt ? $item->endAt->toImmutable()->utc() : null,
                description: $item->description,
                sourceUrl: $sourceUrl,
                externalId: $item->sourceUid,
                rawPayload: ['source_payload_hash' => hash('sha256', $payload)],
                eventType: $item->type,
                timeType: null,
                timePrecision: null,
            );
        }, $items);

        return $this->importFromCandidateItems($sourceName, $sourceUrl, $candidateItems);
    }

    /**
     * @param  array<int, CandidateItem>  $items
     */
    public function importFromCandidateItems(
        string $sourceName,
        string $sourceUrl,
        array $items,
        ?int $eventSourceId = null,
        bool $dryRun = false,
    ): EventImportResult {
        $total = count($items);
        $imported = 0;
        $updated = 0;
        $duplicates = 0;

        foreach ($items as $item) {
            if (trim($item->title) === '') {
                continue;
            }

            $title = $this->normalizeText($item->title);
            if ($title === null) {
                continue;
            }

            $short = $this->normalizeText(Str::limit($item->description ?? $title, 180));
            $description = $this->normalizeText($item->description);

            $rawType = $this->normalizeText($item->eventType) ?? $item->eventType;
            $normalizedType = $this->typeClassifier->classify($rawType, $title);

            $startAt = $item->startsAtUtc;
            $endAt = $item->endsAtUtc;
            $maxAt = $startAt;
            $timeType = EventTime::normalizeType($item->timeType, $startAt, $maxAt);
            $timePrecision = EventTime::normalizePrecision($item->timePrecision, $startAt, $maxAt, $sourceName);

            $sourceUid = $item->externalId ?: $this->buildSourceUidFromNormalized(
                $title,
                $normalizedType ?: 'other',
                $startAt,
                $endAt,
                $maxAt
            );

            $canonicalKey = $this->resolveCanonicalKey(
                providedCanonicalKey: $item->canonicalKey,
                normalizedType: $normalizedType ?: 'other',
                startAt: $startAt,
                title: $title
            );
            $matchedSources = $this->collectCanonicalMatchedSources(
                canonicalKey: $canonicalKey,
                currentSourceName: $sourceName,
                incomingMatchedSources: $item->matchedSources
            );
            $confidenceScore = $this->resolveDeterministicConfidenceScore(
                canonicalKey: $canonicalKey,
                matchedSources: $matchedSources
            );
            $fingerprintV2 = $this->buildFingerprintV2(
                canonicalKey: $canonicalKey,
                normalizedType: $normalizedType ?: 'other',
                startAt: $startAt,
                maxAt: $maxAt,
                title: $title
            );

            $payloadString = json_encode($item->rawPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
            $sourceHash = $this->buildSourceHash($sourceName, $sourceUid, $payloadString);

            $attributes = [
                'event_source_id' => $eventSourceId,
                'source_name' => $sourceName,
                'source_url' => $item->sourceUrl ?: $sourceUrl,
                'source_uid' => $sourceUid,
                'external_id' => $sourceUid,
                'stable_key' => $sourceUid,
                'confidence_score' => $confidenceScore,
                'canonical_key' => $canonicalKey,
                'matched_sources' => $matchedSources,
                'source_hash' => $sourceHash,
                'fingerprint_v2' => $fingerprintV2,

                'title' => $title,
                'original_title' => $title,
                'translated_title' => null,
                'raw_type' => $rawType,
                'type' => $normalizedType,

                'start_at' => $startAt,
                'end_at' => $endAt,
                'max_at' => $maxAt,

                'short' => $short,
                'description' => $description,
                'original_description' => $description,
                'translated_description' => null,
                'translation_status' => EventCandidate::TRANSLATION_PENDING,
                'translation_error' => null,
                'translated_at' => null,
                'raw_payload' => $payloadString,
                'status' => EventCandidate::STATUS_PENDING,
            ];

            if (EventCandidate::supportsTimeColumns()) {
                $attributes['time_type'] = $timeType;
                $attributes['time_precision'] = $timePrecision;
            }

            $existing = $this->findExistingCandidate(
                sourceName: $sourceName,
                eventSourceId: $eventSourceId,
                sourceUid: $sourceUid,
                sourceHash: $sourceHash,
                canonicalKey: $canonicalKey,
                normalizedType: $normalizedType ?: 'other',
                startAt: $startAt,
                maxAt: $maxAt,
                title: $title,
                fingerprintV2: $fingerprintV2,
            );

            if ($existing === null) {
                $candidateId = null;
                if (! $dryRun) {
                    $candidate = EventCandidate::create($attributes);
                    $candidateId = (int) $candidate->id;
                    $this->syncCanonicalSignals(
                        canonicalKey: $canonicalKey,
                        matchedSources: $matchedSources,
                        confidenceScore: $confidenceScore
                    );
                }
                $imported++;
                if ($candidateId !== null) {
                    $this->dispatchCandidateTranslation($candidateId, $sourceName);
                }

                continue;
            }

            if ($this->isCrossSourceMatch($existing, $sourceName, $eventSourceId)) {
                if (! $dryRun) {
                    $this->mergeCrossSourceSignals(
                        existing: $existing,
                        incomingSourceName: $sourceName,
                        canonicalKey: $canonicalKey,
                        incomingMatchedSources: $matchedSources,
                        incomingConfidenceScore: $confidenceScore,
                        incomingFingerprintV2: $fingerprintV2
                    );
                }
                $duplicates++;

                continue;
            }

            if (! $this->shouldUpdateCandidate($existing, $attributes)) {
                $duplicates++;

                continue;
            }

            if ($existing->status !== EventCandidate::STATUS_PENDING) {
                $duplicates++;

                continue;
            }

            if (! $dryRun) {
                $existing->fill($attributes);
                $existing->save();
                $this->syncCanonicalSignals(
                    canonicalKey: $canonicalKey,
                    matchedSources: $matchedSources,
                    confidenceScore: $confidenceScore
                );
                $this->dispatchCandidateTranslation((int) $existing->id, $sourceName);
            }

            $updated++;
        }

        return new EventImportResult($total, $imported, $updated, $duplicates);
    }

    private function buildSourceUidFromNormalized(
        string $title,
        string $type,
        ?\DateTimeInterface $startAt,
        ?\DateTimeInterface $endAt,
        ?\DateTimeInterface $maxAt
    ): string {
        $parts = [
            Str::slug($title, '-'),
            $type ?: 'other',
            $startAt?->format('Y-m-d H:i:s'),
            $endAt?->format('Y-m-d H:i:s'),
            $maxAt?->format('Y-m-d H:i:s'),
        ];

        return trim(implode('|', array_filter(
            $parts,
            static fn ($part) => $part !== null && $part !== ''
        )));
    }

    private function buildSourceHash(string $sourceName, ?string $sourceUid, string $payload): string
    {
        return hash('sha256', implode('|', [
            $sourceName,
            (string) $sourceUid,
            hash('sha256', $payload),
        ]));
    }

    private function findExistingCandidate(
        string $sourceName,
        ?int $eventSourceId,
        ?string $sourceUid,
        string $sourceHash,
        ?string $canonicalKey,
        string $normalizedType,
        ?\DateTimeInterface $startAt,
        ?\DateTimeInterface $maxAt,
        string $title,
        string $fingerprintV2,
    ): ?EventCandidate {
        $scope = EventCandidate::query()
            ->when(
                $eventSourceId,
                fn ($query) => $query->where('event_source_id', $eventSourceId),
                fn ($query) => $query->where('source_name', $sourceName)
            );

        if ($sourceUid !== null && $sourceUid !== '') {
            $bySourceUid = (clone $scope)
                ->where(function ($query) use ($sourceUid) {
                    $query->where('external_id', $sourceUid)
                        ->orWhere('source_uid', $sourceUid)
                        ->orWhere('stable_key', $sourceUid);
                })
                ->orderByDesc('id')
                ->first();
            if ($bySourceUid !== null) {
                return $bySourceUid;
            }
        }

        $byFingerprint = (clone $scope)
            ->where('fingerprint_v2', $fingerprintV2)
            ->orderByDesc('id')
            ->first();
        if ($byFingerprint !== null) {
            return $byFingerprint;
        }

        if ($canonicalKey !== null && $canonicalKey !== '') {
            $byCanonical = (clone $scope)
                ->where('canonical_key', $canonicalKey)
                ->orderByDesc('id')
                ->first();
            if ($byCanonical !== null) {
                return $byCanonical;
            }
        }

        $bySourceHash = (clone $scope)
            ->where('source_hash', $sourceHash)
            ->orderByDesc('id')
            ->first();
        if ($bySourceHash !== null) {
            return $bySourceHash;
        }

        $byCrossSourceCanonical = $this->findCrossSourceCanonicalCandidate(
            sourceName: $sourceName,
            eventSourceId: $eventSourceId,
            canonicalKey: $canonicalKey,
            fingerprintV2: $fingerprintV2
        );
        if ($byCrossSourceCanonical !== null) {
            return $byCrossSourceCanonical;
        }

        return $this->findFuzzyExistingCandidate(
            scope: $scope,
            normalizedType: $normalizedType,
            startAt: $startAt,
            maxAt: $maxAt,
            title: $title
        );
    }

    private function shouldUpdateCandidate(EventCandidate $candidate, array $attributes): bool
    {
        $fields = [
            'event_source_id',
            'source_name',
            'source_url',
            'source_uid',
            'external_id',
            'stable_key',
            'confidence_score',
            'canonical_key',
            'matched_sources',
            'source_hash',
            'fingerprint_v2',
            'title',
            'raw_type',
            'type',
            'start_at',
            'end_at',
            'max_at',
            'short',
            'description',
            'raw_payload',
        ];

        foreach ($fields as $field) {
            $current = $this->normalizeComparableValue($candidate->getAttribute($field), $field);
            $incoming = $this->normalizeComparableValue($attributes[$field] ?? null, $field);
            if ($current !== $incoming) {
                return true;
            }
        }

        return false;
    }

    private function isCrossSourceMatch(EventCandidate $candidate, string $sourceName, ?int $eventSourceId): bool
    {
        $existingSource = strtolower(trim((string) $candidate->source_name));
        $incomingSource = strtolower(trim($sourceName));

        if ($existingSource !== '' && $incomingSource !== '' && $existingSource !== $incomingSource) {
            return true;
        }

        if ($eventSourceId === null || $candidate->event_source_id === null) {
            return false;
        }

        return (int) $candidate->event_source_id !== $eventSourceId;
    }

    private function findCrossSourceCanonicalCandidate(
        string $sourceName,
        ?int $eventSourceId,
        ?string $canonicalKey,
        string $fingerprintV2
    ): ?EventCandidate {
        $query = EventCandidate::query()
            ->where(function ($scope) use ($sourceName, $eventSourceId) {
                if ($eventSourceId !== null) {
                    $scope->where(function ($q) use ($eventSourceId) {
                        $q->whereNotNull('event_source_id')
                            ->where('event_source_id', '!=', $eventSourceId);
                    })->orWhere(function ($q) use ($sourceName) {
                        $q->whereNull('event_source_id')
                            ->where('source_name', '!=', $sourceName);
                    });

                    return;
                }

                $scope->where('source_name', '!=', $sourceName);
            });

        $normalizedCanonical = $this->normalizeText($canonicalKey);
        if ($normalizedCanonical !== null) {
            $query->where('canonical_key', $normalizedCanonical);
        } else {
            $query->where('fingerprint_v2', $fingerprintV2);
        }

        return $query
            ->orderByDesc('confidence_score')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @param  array<int,string>|null  $incomingMatchedSources
     */
    private function mergeCrossSourceSignals(
        EventCandidate $existing,
        string $incomingSourceName,
        ?string $canonicalKey,
        ?array $incomingMatchedSources,
        ?float $incomingConfidenceScore,
        string $incomingFingerprintV2
    ): void {
        $existingCanonical = $this->normalizeText($existing->canonical_key);
        $resolvedCanonical = $existingCanonical ?: $this->normalizeText($canonicalKey);

        $mergedSources = $this->normalizeMatchedSources(array_merge(
            is_array($existing->matched_sources) ? $existing->matched_sources : [],
            $incomingMatchedSources ?? [],
            [(string) $existing->source_name, $incomingSourceName]
        ));

        $deterministicScore = $this->resolveDeterministicConfidenceScore($resolvedCanonical, $mergedSources);
        $scoreCandidates = [];
        if ($deterministicScore !== null) {
            $scoreCandidates[] = $deterministicScore;
        }
        if ($incomingConfidenceScore !== null) {
            $scoreCandidates[] = $incomingConfidenceScore;
        }
        if ($existing->confidence_score !== null && is_numeric((string) $existing->confidence_score)) {
            $scoreCandidates[] = (float) $existing->confidence_score;
        }
        $resolvedScore = $scoreCandidates !== [] ? round(max($scoreCandidates), 2) : null;

        $hasChanges = false;
        if ($resolvedCanonical !== null && $existingCanonical !== $resolvedCanonical) {
            $existing->canonical_key = $resolvedCanonical;
            $hasChanges = true;
        }

        if ($this->normalizeComparableValue($existing->matched_sources, 'matched_sources') !== $this->normalizeComparableValue($mergedSources, 'matched_sources')) {
            $existing->matched_sources = $mergedSources;
            $hasChanges = true;
        }

        if ($this->normalizeComparableValue($existing->confidence_score, 'confidence_score') !== $this->normalizeComparableValue($resolvedScore, 'confidence_score')) {
            $existing->confidence_score = $resolvedScore;
            $hasChanges = true;
        }

        $existingFingerprint = $this->normalizeText((string) $existing->fingerprint_v2);
        if ($existingFingerprint === null && $incomingFingerprintV2 !== '') {
            $existing->fingerprint_v2 = $incomingFingerprintV2;
            $hasChanges = true;
        }

        if ($hasChanges) {
            $existing->save();
        }

        if ($resolvedCanonical !== null) {
            $this->syncCanonicalSignals(
                canonicalKey: $resolvedCanonical,
                matchedSources: $mergedSources,
                confidenceScore: $resolvedScore
            );
        }
    }

    private function buildFingerprintV2(
        ?string $canonicalKey,
        string $normalizedType,
        ?\DateTimeInterface $startAt,
        ?\DateTimeInterface $maxAt,
        string $title
    ): string {
        $parts = [];

        $normalizedCanonical = $this->normalizeForSimilarity($canonicalKey);
        if ($normalizedCanonical !== null) {
            $parts[] = 'ck:'.$normalizedCanonical;
        }

        $normalizedTypeValue = $this->normalizeForSimilarity($normalizedType);
        if ($normalizedTypeValue !== null) {
            $parts[] = 'tp:'.$normalizedTypeValue;
        }

        $anchor = $this->resolveReferenceMoment($startAt, $maxAt);
        if ($anchor !== null) {
            $parts[] = 'dt:'.CarbonImmutable::instance($anchor)->utc()->toDateString();
        }

        $normalizedTitle = $this->normalizeForSimilarity($title);
        if ($normalizedTitle !== null) {
            $parts[] = 'ttl:'.$normalizedTitle;
        }

        return hash('sha256', implode('|', $parts));
    }

    private function findFuzzyExistingCandidate(
        \Illuminate\Database\Eloquent\Builder $scope,
        string $normalizedType,
        ?\DateTimeInterface $startAt,
        ?\DateTimeInterface $maxAt,
        string $title
    ): ?EventCandidate {
        if (! (bool) config('events.deduplication.fuzzy.enabled', true)) {
            return null;
        }

        $anchor = $this->resolveReferenceMoment($startAt, $maxAt);
        $titleForSimilarity = $this->normalizeForSimilarity($title);
        if ($anchor === null || $titleForSimilarity === null) {
            return null;
        }

        $windowHours = max(1, (int) config('events.deduplication.fuzzy.window_hours', 36));
        $from = CarbonImmutable::instance($anchor)->subHours($windowHours);
        $to = CarbonImmutable::instance($anchor)->addHours($windowHours);

        $threshold = (float) config('events.deduplication.fuzzy.min_title_similarity', 0.86);
        $threshold = max(0.5, min(1.0, $threshold));

        $candidateRows = (clone $scope)
            ->where('type', $normalizedType)
            ->where(function ($query) use ($from, $to) {
                $query->whereBetween('start_at', [$from, $to])
                    ->orWhereBetween('max_at', [$from, $to]);
            })
            ->orderByDesc('id')
            ->limit(40)
            ->get(['id', 'title']);

        $best = null;
        $bestScore = 0.0;

        foreach ($candidateRows as $row) {
            $existingTitle = $this->normalizeForSimilarity((string) $row->title);
            if ($existingTitle === null) {
                continue;
            }

            $score = $this->titleSimilarity($titleForSimilarity, $existingTitle);
            if ($score < $threshold || $score <= $bestScore) {
                continue;
            }

            $best = $row;
            $bestScore = $score;
        }

        return $best instanceof EventCandidate ? $best : null;
    }

    private function resolveReferenceMoment(
        ?\DateTimeInterface $startAt,
        ?\DateTimeInterface $maxAt
    ): ?\DateTimeInterface {
        if ($startAt !== null) {
            return $startAt;
        }

        if ($maxAt !== null) {
            return $maxAt;
        }

        return null;
    }

    private function normalizeForSimilarity(?string $value): ?string
    {
        $normalized = $this->normalizeText($value);
        if ($normalized === null) {
            return null;
        }

        if (function_exists('mb_strtolower')) {
            $normalized = mb_strtolower($normalized, 'UTF-8');
        } else {
            $normalized = strtolower($normalized);
        }

        $normalized = preg_replace('/[^\pL\pN\s]/u', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;
        $normalized = trim($normalized);

        return $normalized !== '' ? $normalized : null;
    }

    private function titleSimilarity(string $left, string $right): float
    {
        if ($left === '' || $right === '') {
            return 0.0;
        }

        similar_text($left, $right, $percent);

        return round(((float) $percent) / 100, 4);
    }

    private function normalizeComparableValue(mixed $value, ?string $field = null): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($field === 'confidence_score') {
            return number_format((float) $value, 2, '.', '');
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        }

        if (is_array($value)) {
            $normalized = array_values(array_filter(array_map(
                static fn (mixed $item): string => strtolower(trim((string) $item)),
                $value
            ), static fn (string $item): bool => $item !== ''));
            sort($normalized);

            $json = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            return $json !== false ? $json : null;
        }

        return trim((string) $value);
    }

    private function normalizeText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $s = trim($value);
        if ($s === '') {
            return null;
        }

        $s = str_replace(['&amp;#', '&amp;nbsp;'], ['&#', ' '], $s);
        $s = preg_replace('/&#(\d+)(?!;)/', '&#$1;', $s) ?? $s;
        $s = preg_replace('/&#x([0-9a-fA-F]+)(?!;)/', '&#x$1;', $s) ?? $s;

        for ($i = 0; $i < 2; $i++) {
            $decoded = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($decoded === $s) {
                break;
            }
            $s = $decoded;
        }

        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
        $s = trim($s);

        return $s !== '' ? $s : null;
    }

    private function dispatchCandidateTranslation(int $candidateId, string $sourceName): void
    {
        if (! $this->shouldDispatchEventTranslation($sourceName)) {
            return;
        }

        try {
            $queueConnection = strtolower(trim((string) config('queue.default', 'sync')));
            $allowSyncQueue = (bool) config('translation.allow_sync_queue', false);

            // During crawl/import we should not block the HTTP request with translation
            // when queue driver is sync. Keep candidates pending and let explicit
            // retranslate/manual actions handle immediate translation if needed.
            if ($queueConnection === 'sync' && ! $allowSyncQueue) {
                Log::info('Skipped event candidate translation dispatch during import (sync queue).', [
                    'candidate_id' => $candidateId,
                    'source_name' => $sourceName,
                ]);

                return;
            }

            if ($queueConnection === 'sync' && $allowSyncQueue) {
                TranslateEventCandidateJob::dispatchSync($candidateId);

                return;
            }

            TranslateEventCandidateJob::dispatch($candidateId)->afterCommit();
        } catch (Throwable $exception) {
            Log::warning('Event candidate translation dispatch failed', [
                'candidate_id' => $candidateId,
                'source_name' => $sourceName,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function shouldDispatchEventTranslation(string $sourceName): bool
    {
        if (! (bool) config('translation.events.enabled', true)) {
            return false;
        }

        if ($sourceName === 'manual') {
            return false;
        }

        return true;
    }

    private function resolveCanonicalKey(
        ?string $providedCanonicalKey,
        string $normalizedType,
        ?\DateTimeInterface $startAt,
        string $title
    ): ?string {
        $canonicalKey = $this->normalizeText($providedCanonicalKey);
        if ($canonicalKey !== null) {
            return $canonicalKey;
        }

        $generated = $this->canonicalKeyService->make(
            type: $normalizedType,
            date: $startAt ? CarbonImmutable::instance($startAt)->utc() : null,
            title: $title
        );

        return $this->normalizeText($generated);
    }

    /**
     * @param  array<int,mixed>|null  $incomingMatchedSources
     * @return array<int,string>|null
     */
    private function collectCanonicalMatchedSources(
        ?string $canonicalKey,
        string $currentSourceName,
        ?array $incomingMatchedSources
    ): ?array {
        $sources = [$currentSourceName];

        if ($incomingMatchedSources !== null) {
            $sources = array_merge($sources, $incomingMatchedSources);
        }

        if ($canonicalKey !== null && $canonicalKey !== '') {
            $existing = EventCandidate::query()
                ->where('canonical_key', $canonicalKey)
                ->get(['source_name', 'matched_sources']);

            foreach ($existing as $row) {
                $sources[] = (string) $row->source_name;

                $matched = $row->matched_sources;
                if (is_array($matched)) {
                    $sources = array_merge($sources, $matched);
                }
            }
        }

        return $this->normalizeMatchedSources($sources);
    }

    /**
     * @param  array<int,string>|null  $matchedSources
     */
    private function resolveDeterministicConfidenceScore(?string $canonicalKey, ?array $matchedSources): ?float
    {
        if ($canonicalKey === null || $canonicalKey === '') {
            return null;
        }

        $count = is_array($matchedSources) ? count($matchedSources) : 0;

        return $count >= 2 ? 1.0 : 0.7;
    }

    /**
     * @param  array<int,string>|null  $matchedSources
     */
    private function syncCanonicalSignals(?string $canonicalKey, ?array $matchedSources, ?float $confidenceScore): void
    {
        if ($canonicalKey === null || $canonicalKey === '') {
            return;
        }

        EventCandidate::query()
            ->where('canonical_key', $canonicalKey)
            ->update([
                'matched_sources' => $matchedSources,
                'confidence_score' => $confidenceScore,
            ]);
    }

    /**
     * @param  array<int,mixed>|null  $matchedSources
     * @return array<int,string>|null
     */
    private function normalizeMatchedSources(?array $matchedSources): ?array
    {
        if ($matchedSources === null) {
            return null;
        }

        $normalized = array_values(array_unique(array_filter(array_map(
            static fn (mixed $item): string => strtolower(trim((string) $item)),
            $matchedSources
        ), static fn (string $item): bool => $item !== '')));

        if ($normalized === []) {
            return null;
        }

        sort($normalized);

        return $normalized;
    }
}
