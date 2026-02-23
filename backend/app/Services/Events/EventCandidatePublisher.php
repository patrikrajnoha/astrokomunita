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

                $existingApproved = Event::query()
                    ->where('source_name', $candidate->source_name)
                    ->where('source_uid', $candidate->source_uid)
                    ->first();

                if ($existingApproved) {
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

            $event = new Event();
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
            $event->short = $candidate->short;
            $event->visibility = $candidate->visibility ?? config('events.default_visibility', 1);
            $event->source_name = $candidate->source_name;
            $event->source_uid = $candidate->source_uid;
            $event->source_hash = $candidate->source_hash;

            $this->syncEventCanonicalSignals($event, $candidate, save: false);

            try {
                $event->save();
            } catch (QueryException $exception) {
                if (($exception->errorInfo[0] ?? null) === '23000') {
                    $existing = Event::query()
                        ->where('source_name', $candidate->source_name)
                        ->where('source_uid', $candidate->source_uid)
                        ->first();

                    if ($existing) {
                        $this->syncEventCanonicalSignals($existing, $candidate);
                        $this->updateCandidateAsApproved($candidate, $existing->id, $reviewerUserId);
                        return $existing;
                    }
                }

                throw $exception;
            }

            $this->updateCandidateAsApproved($candidate, $event->id, $reviewerUserId);

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

    /**
     * @param array<int,string>|null $existing
     * @param array<int,string>|null $incoming
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
     * @param array<int,mixed>|null $values
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
     * @param array<int,string> $mergedSources
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

    private function normalizeText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim($value);
        return $normalized !== '' ? $normalized : null;
    }
}

