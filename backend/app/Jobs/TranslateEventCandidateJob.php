<?php

namespace App\Jobs;

use App\Models\EventCandidate;
use App\Services\Translation\TranslationServiceException;
use App\Services\TranslationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class TranslateEventCandidateJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 4;
    public int $uniqueFor = 600;

    public function __construct(
        public readonly int $candidateId,
        public readonly bool $force = false,
    ) {
    }

    /**
     * @return array<int,int>
     */
    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function uniqueId(): string
    {
        return 'event-candidate-translation-' . $this->candidateId . '-' . ($this->force ? 'force' : 'normal');
    }

    public function handle(TranslationService $translationService): void
    {
        $candidate = EventCandidate::query()->find($this->candidateId);
        if (! $candidate) {
            return;
        }

        if (
            ! $this->force
            && $candidate->translation_status === EventCandidate::TRANSLATION_DONE
            && filled($candidate->translated_title)
            && ($candidate->description === null || filled($candidate->translated_description))
        ) {
            return;
        }

        $originalTitle = (string) ($candidate->original_title ?: $candidate->title);
        $originalDescription = $candidate->original_description ?? $candidate->description;

        $candidate->update([
            'original_title' => $originalTitle,
            'original_description' => $originalDescription,
            'translation_status' => EventCandidate::TRANSLATION_PENDING,
            'translation_error' => null,
        ]);

        try {
            $translatedTitle = $translationService->translateEnToSk($originalTitle, 'astronomy');
            $translatedDescription = $originalDescription !== null
                ? $translationService->translateEnToSk((string) $originalDescription, 'astronomy')
                : null;

            $candidate->update([
                'translated_title' => $translatedTitle,
                'translated_description' => $translatedDescription,
                'translation_status' => EventCandidate::TRANSLATION_DONE,
                'translation_error' => null,
                'translated_at' => now(),
            ]);

            Log::info('Event candidate translated', [
                'event_candidate_id' => $candidate->id,
                'force' => $this->force,
            ]);
        } catch (TranslationServiceException $exception) {
            $candidate->update([
                'translation_status' => EventCandidate::TRANSLATION_FAILED,
                'translation_error' => $exception->errorCode(),
            ]);

            Log::warning('Event candidate translation failed', [
                'event_candidate_id' => $candidate->id,
                'error_code' => $exception->errorCode(),
                'status_code' => $exception->statusCode(),
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
