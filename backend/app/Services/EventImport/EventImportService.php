<?php

namespace App\Services\EventImport;

use App\Jobs\TranslateEventCandidateJob;
use App\Models\EventCandidate;
use App\Services\Crawlers\CandidateItem;
use App\Services\EventImport\Parsers\EventSourceParser;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Throwable;

class EventImportService
{
    public function __construct(
        private HtmlSourceFetcher $fetcher,
        private EventTypeClassifier $typeClassifier,
    ) {
    }

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
            );
        }, $items);

        return $this->importFromCandidateItems($sourceName, $sourceUrl, $candidateItems);
    }

    /**
     * @param array<int, CandidateItem> $items
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

            $sourceUid = $item->externalId ?: $this->buildSourceUidFromNormalized(
                $title,
                $normalizedType ?: 'other',
                $startAt,
                $endAt,
                $maxAt
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
                'source_hash' => $sourceHash,

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

            $existing = $this->findExistingCandidate(
                sourceName: $sourceName,
                eventSourceId: $eventSourceId,
                sourceUid: $sourceUid,
                sourceHash: $sourceHash
            );

            if ($existing === null) {
                $candidateId = null;
                if (!$dryRun) {
                    $candidate = EventCandidate::create($attributes);
                    $candidateId = (int) $candidate->id;
                }
                $imported++;
                if ($candidateId !== null) {
                    $this->dispatchCandidateTranslation($candidateId, $sourceName);
                }
                continue;
            }

            if (!$this->shouldUpdateCandidate($existing, $attributes)) {
                $duplicates++;
                continue;
            }

            if ($existing->status !== EventCandidate::STATUS_PENDING) {
                $duplicates++;
                continue;
            }

            if (!$dryRun) {
                $existing->fill($attributes);
                $existing->save();
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
        string $sourceHash
    ): ?EventCandidate {
        return EventCandidate::query()
            ->when(
                $eventSourceId,
                fn ($query) => $query->where('event_source_id', $eventSourceId),
                fn ($query) => $query->where('source_name', $sourceName)
            )
            ->where(function ($query) use ($sourceUid, $sourceHash) {
                if ($sourceUid !== null && $sourceUid !== '') {
                    $query->where('external_id', $sourceUid)
                        ->orWhere('source_uid', $sourceUid)
                        ->orWhere('stable_key', $sourceUid)
                        ->orWhere('source_hash', $sourceHash);

                    return;
                }

                $query->where('source_hash', $sourceHash);
            })
            ->orderByDesc('id')
            ->first();
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
            'source_hash',
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
            $current = $this->normalizeComparableValue($candidate->getAttribute($field));
            $incoming = $this->normalizeComparableValue($attributes[$field] ?? null);
            if ($current !== $incoming) {
                return true;
            }
        }

        return false;
    }

    private function normalizeComparableValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
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

        if (! (bool) config('translation.allow_sync_queue', false) && config('queue.default') === 'sync') {
            return false;
        }

        return true;
    }
}
