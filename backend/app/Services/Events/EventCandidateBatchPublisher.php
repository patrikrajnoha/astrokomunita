<?php

namespace App\Services\Events;

use App\Jobs\TranslateEventCandidateJob;
use App\Models\Event;
use App\Models\EventCandidate;
use Illuminate\Support\Facades\Log;

class EventCandidateBatchPublisher
{
    public function __construct(
        private readonly EventCandidatePublisher $publisher,
    ) {
    }

    public function approvePendingCandidate(int $candidateId, int $reviewerUserId, string $publishGenerationMode): bool
    {
        $candidate = EventCandidate::query()->find($candidateId);
        if (! $candidate || $candidate->status !== EventCandidate::STATUS_PENDING) {
            return false;
        }

        $normalizedPublishMode = $this->normalizePublishGenerationMode($publishGenerationMode);
        $effectiveGenerationMode = $this->resolveCandidatePublishGenerationMode($candidate, $normalizedPublishMode);

        $this->archiveCandidateDescriptionVariant(
            candidate: $candidate,
            reason: 'approve_batch_before_mode_switch',
            requestedPublishMode: $normalizedPublishMode
        );

        $this->runSynchronousRetranslationForPublish((int) $candidate->id, $effectiveGenerationMode);
        $candidate->refresh();

        $event = $this->publisher->approve($candidate, $reviewerUserId);
        $candidate->refresh();

        $publishedEventId = (int) ($candidate->published_event_id ?? 0);
        $isApproved = (string) ($candidate->status ?? '') === EventCandidate::STATUS_APPROVED;
        $isConsistent = $isApproved
            && $publishedEventId > 0
            && $publishedEventId === (int) ($event->id ?? 0)
            && Event::query()->whereKey($publishedEventId)->exists();

        if (! $isConsistent) {
            Log::warning('Batch candidate publish ended in inconsistent state.', [
                'candidate_id' => $candidateId,
                'candidate_status' => (string) ($candidate->status ?? ''),
                'published_event_id' => $candidate->published_event_id,
                'returned_event_id' => (int) ($event->id ?? 0),
                'requested_publish_mode' => $publishGenerationMode,
                'effective_publish_mode' => $effectiveGenerationMode,
            ]);
        }

        return $isConsistent;
    }

    private function normalizePublishGenerationMode(mixed $value): string
    {
        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, ['template', 'ai', 'mix'], true) ? $normalized : 'template';
    }

    private function resolveCandidatePublishGenerationMode(EventCandidate $candidate, string $publishGenerationMode): string
    {
        if ($publishGenerationMode !== 'mix') {
            return $publishGenerationMode;
        }

        $currentMode = strtolower(trim((string) ($candidate->translation_mode ?? '')));
        if ($currentMode === EventCandidate::TRANSLATION_MODE_TEMPLATE) {
            return 'template';
        }
        if ($currentMode === EventCandidate::TRANSLATION_MODE_MANUAL) {
            return 'manual';
        }

        return 'ai';
    }

    private function runSynchronousRetranslationForPublish(int $candidateId, string $requestedMode): void
    {
        if ($requestedMode === 'manual') {
            return;
        }

        TranslateEventCandidateJob::dispatchSync($candidateId, true, $requestedMode);
    }

    private function archiveCandidateDescriptionVariant(
        EventCandidate $candidate,
        string $reason,
        ?string $requestedPublishMode = null
    ): void {
        $title = trim((string) ($candidate->translated_title ?: $candidate->title ?: ''));
        $description = trim((string) ($candidate->translated_description ?: $candidate->description ?: ''));
        $short = trim((string) ($candidate->short ?? ''));

        if ($title === '' && $description === '' && $short === '') {
            return;
        }

        $payload = $this->decodeCandidateRawPayload((string) ($candidate->raw_payload ?? ''));
        $variants = is_array($payload['description_variants'] ?? null)
            ? array_values(array_filter($payload['description_variants'], static fn ($item): bool => is_array($item)))
            : [];

        $variants[] = array_filter([
            'captured_at' => now()->toIso8601String(),
            'reason' => $reason,
            'requested_publish_mode' => $requestedPublishMode ?: null,
            'mode' => (string) ($candidate->translation_mode ?? ''),
            'translation_status' => (string) ($candidate->translation_status ?? ''),
            'title' => $title !== '' ? $title : null,
            'description' => $description !== '' ? $description : null,
            'short' => $short !== '' ? $short : null,
        ], static fn ($value): bool => $value !== null);

        if (count($variants) > 30) {
            $variants = array_slice($variants, -30);
        }

        $payload['description_variants'] = $variants;
        $encodedPayload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (! is_string($encodedPayload) || trim($encodedPayload) === '') {
            return;
        }

        $candidate->forceFill([
            'raw_payload' => $encodedPayload,
        ])->save();
    }

    /**
     * @return array<string,mixed>
     */
    private function decodeCandidateRawPayload(string $rawPayload): array
    {
        $trimmed = trim($rawPayload);
        if ($trimmed === '') {
            return [];
        }

        $decoded = json_decode($trimmed, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        return [
            '_source_raw_payload_text' => $rawPayload,
        ];
    }
}
