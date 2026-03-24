<?php

namespace App\Services\Events;

use App\Models\Event;
use App\Models\EventCandidate;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class EventCandidatePublisher
{
    public function __construct(
        private readonly EventFeedRealtimePublisher $eventFeedRealtimePublisher,
        private readonly EventDescriptionOriginRecorder $originRecorder,
    ) {}

    public function approve(EventCandidate $candidate, int $reviewerUserId): Event
    {
        return DB::transaction(function () use ($candidate, $reviewerUserId) {
            $candidate->refresh();

            if ($candidate->status === EventCandidate::STATUS_APPROVED) {
                if ($candidate->published_event_id) {
                    $event = Event::findOrFail($candidate->published_event_id);
                    $this->syncEventCanonicalSignals($event, $candidate);

                    return $event;
                }

                $existingApproved = $this->findExistingEventForCandidate($candidate);
                if ($existingApproved !== null) {
                    $this->syncEventCanonicalSignals($existingApproved, $candidate);
                    $this->updateCandidateAsApproved($candidate, $existingApproved->id, $reviewerUserId);

                    return $existingApproved;
                }
            }

            if (
                $candidate->status !== EventCandidate::STATUS_PENDING
                && $candidate->status !== EventCandidate::STATUS_APPROVED
            ) {
                throw new RuntimeException("Candidate is not in a valid state for approval (current: {$candidate->status}).");
            }

            $existingEvent = $this->findExistingEventForCandidate($candidate);
            if ($existingEvent !== null) {
                $this->syncEventCanonicalSignals($existingEvent, $candidate);
                $this->updateCandidateAsApproved($candidate, $existingEvent->id, $reviewerUserId);

                return $existingEvent;
            }

            $event = new Event;
            $event->title = filled($candidate->translated_title)
                ? (string) $candidate->translated_title
                : (string) $candidate->title;
            $event->description = filled($candidate->translated_description)
                ? (string) $candidate->translated_description
                : $candidate->description;
            $event->type = $candidate->type ?? 'other';
            $event->start_at = $candidate->start_at;
            $event->end_at = $candidate->end_at;
            $event->max_at = $candidate->max_at;
            $event->time_type = $candidate->time_type;
            $event->time_precision = $candidate->time_precision;
            $event->short = $candidate->short;
            $event->visibility = $candidate->visibility ?? config('events.default_visibility', 1);
            $event->source_name = $candidate->source_name;
            $event->source_uid = $candidate->source_uid;
            $event->source_hash = $candidate->source_hash;
            $event->fingerprint_v2 = $candidate->fingerprint_v2;

            $this->syncEventCanonicalSignals($event, $candidate, save: false);

            try {
                $event->save();
            } catch (QueryException $exception) {
                if (($exception->errorInfo[0] ?? null) === '23000') {
                    $existing = $this->findExistingEventForCandidate($candidate);

                    if ($existing) {
                        $this->syncEventCanonicalSignals($existing, $candidate);
                        $this->updateCandidateAsApproved($candidate, $existing->id, $reviewerUserId);

                        return $existing;
                    }
                }

                throw $exception;
            }

            $this->updateCandidateAsApproved($candidate, $event->id, $reviewerUserId);
            $this->originRecorder->record(
                event: $event,
                source: filled($candidate->translated_description)
                    ? 'candidate_publish_translation'
                    : 'candidate_publish_import',
                sourceDetail: filled($candidate->translated_description)
                    ? 'translated_description'
                    : 'original_description',
                candidateId: (int) $candidate->id,
                meta: [
                    'candidate_status' => (string) ($candidate->status ?? ''),
                    'translation_status' => (string) ($candidate->translation_status ?? ''),
                    'translation_error' => $candidate->translation_error,
                ]
            );
            $this->eventFeedRealtimePublisher->publish($event);

            return $event;
        });
    }

    public function reject(EventCandidate $candidate, int $reviewerUserId, string $reason): void
    {
        DB::transaction(function () use ($candidate, $reviewerUserId, $reason) {
            $candidate->refresh();

            if ($candidate->status !== EventCandidate::STATUS_PENDING) {
                throw new RuntimeException("Candidate is not pending (current: {$candidate->status}).");
            }

            $candidate->status = EventCandidate::STATUS_REJECTED;
            $candidate->reviewed_by = $reviewerUserId;
            $candidate->reviewed_at = CarbonImmutable::now();
            $candidate->reject_reason = $reason;
            $candidate->published_event_id = null;
            $candidate->save();
        });
    }

    private function updateCandidateAsApproved(EventCandidate $candidate, int $eventId, int $reviewerUserId): void
    {
        $candidate->refresh();
        $candidate->status = EventCandidate::STATUS_APPROVED;
        $candidate->reviewed_by = $reviewerUserId;
        $candidate->reviewed_at = CarbonImmutable::now();
        $candidate->published_event_id = $eventId;
        $candidate->reject_reason = null;
        $candidate->save();
    }

    private function syncEventCanonicalSignals(Event $event, EventCandidate $candidate, bool $save = true): void
    {
        $candidateCanonical = $this->normalizeText($candidate->canonical_key);
        $eventCanonical = $this->normalizeText($event->canonical_key);
        $event->canonical_key = $candidateCanonical ?: $eventCanonical;

        $candidateFingerprint = $this->normalizeText($candidate->fingerprint_v2);
        $eventFingerprint = $this->normalizeText($event->fingerprint_v2);
        $event->fingerprint_v2 = $eventFingerprint ?: $candidateFingerprint;

        $mergedSources = $this->mergeMatchedSources(
            $this->normalizeMatchedSources($event->matched_sources),
            $this->normalizeMatchedSources($candidate->matched_sources),
            (string) $event->source_name,
            (string) $candidate->source_name
        );
        $event->matched_sources = $mergedSources !== [] ? $mergedSources : null;

        $event->confidence_score = $this->resolveConfidenceScore(
            $event->confidence_score,
            $candidate->confidence_score,
            $mergedSources
        );

        if ($save) {
            $event->save();
        }
    }

    private function findExistingEventForCandidate(EventCandidate $candidate): ?Event
    {
        $sourceUid = $this->normalizeText($candidate->source_uid);
        if ($sourceUid !== null) {
            $bySourceUid = Event::query()
                ->where('source_name', (string) $candidate->source_name)
                ->where('source_uid', $sourceUid)
                ->first();
            if ($bySourceUid !== null) {
                return $bySourceUid;
            }
        }

        $fingerprint = $this->normalizeText($candidate->fingerprint_v2);
        if ($fingerprint !== null) {
            $byFingerprint = Event::query()
                ->where('fingerprint_v2', $fingerprint)
                ->orderByDesc('id')
                ->first();
            if ($byFingerprint !== null) {
                return $byFingerprint;
            }
        }

        $canonical = $this->normalizeText($candidate->canonical_key);
        if ($canonical !== null) {
            $byCanonical = Event::query()
                ->where('canonical_key', $canonical)
                ->orderByDesc('id')
                ->first();
            if ($byCanonical !== null) {
                return $byCanonical;
            }
        }

        return $this->findFuzzyPublishedEventMatch($candidate);
    }

    private function findFuzzyPublishedEventMatch(EventCandidate $candidate): ?Event
    {
        if (! (bool) config('events.deduplication.publish_fuzzy.enabled', true)) {
            return null;
        }

        $candidateTitle = $this->normalizeForSimilarity((string) $candidate->title);
        $candidateType = $this->normalizeText((string) $candidate->type);
        $anchor = $candidate->start_at ?? $candidate->max_at;
        if ($candidateTitle === null || $candidateType === null || ! $anchor instanceof \DateTimeInterface) {
            return null;
        }

        $windowHours = max(1, (int) config('events.deduplication.publish_fuzzy.window_hours', 36));
        $from = CarbonImmutable::instance($anchor)->subHours($windowHours);
        $to = CarbonImmutable::instance($anchor)->addHours($windowHours);

        $threshold = (float) config('events.deduplication.publish_fuzzy.min_title_similarity', 0.86);
        $threshold = max(0.5, min(1.0, $threshold));

        $rows = Event::query()
            ->where('type', $candidateType)
            ->where(function ($query) use ($from, $to) {
                $query->whereBetween('start_at', [$from, $to])
                    ->orWhereBetween('max_at', [$from, $to]);
            })
            ->orderByDesc('id')
            ->limit(50)
            ->get(['id', 'title']);

        $best = null;
        $bestScore = 0.0;

        foreach ($rows as $row) {
            $eventTitle = $this->normalizeForSimilarity((string) $row->title);
            if ($eventTitle === null) {
                continue;
            }

            $score = $this->titleSimilarity($candidateTitle, $eventTitle);
            if ($score < $threshold || $score <= $bestScore) {
                continue;
            }

            $best = $row;
            $bestScore = $score;
        }

        return $best instanceof Event ? $best : null;
    }

    /**
     * @param  array<int,string>|null  $existing
     * @param  array<int,string>|null  $incoming
     * @return array<int,string>
     */
    private function mergeMatchedSources(?array $existing, ?array $incoming, string $eventSourceName, string $candidateSourceName): array
    {
        $values = array_merge(
            $existing ?? [],
            $incoming ?? [],
            [$eventSourceName, $candidateSourceName]
        );

        return $this->normalizeMatchedSources($values) ?? [];
    }

    /**
     * @param  array<int,mixed>|null  $values
     * @return array<int,string>|null
     */
    private function normalizeMatchedSources(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        $normalized = array_values(array_unique(array_filter(array_map(
            static fn (mixed $item): string => strtolower(trim((string) $item)),
            $values
        ), static fn (string $item): bool => $item !== '')));

        if ($normalized === []) {
            return null;
        }

        sort($normalized);

        return $normalized;
    }

    /**
     * @param  array<int,string>  $mergedSources
     */
    private function resolveConfidenceScore(mixed $eventScore, mixed $candidateScore, array $mergedSources): ?float
    {
        $values = [];

        if ($eventScore !== null && is_numeric((string) $eventScore)) {
            $values[] = (float) $eventScore;
        }
        if ($candidateScore !== null && is_numeric((string) $candidateScore)) {
            $values[] = (float) $candidateScore;
        }

        $deterministic = match (count($mergedSources)) {
            0 => null,
            1 => 0.7,
            default => 1.0,
        };
        if ($deterministic !== null) {
            $values[] = $deterministic;
        }

        if ($values === []) {
            return null;
        }

        return round(max($values), 2);
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

    private function normalizeText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim($value);

        return $normalized !== '' ? $normalized : null;
    }
}
