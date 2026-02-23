<?php

namespace App\Jobs;

use App\Models\EventCandidate;
use App\Services\AI\OllamaRefinementService;
use App\Services\Translation\TranslationServiceException;
use App\Services\TranslationService;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TranslateEventCandidateJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private const REFINED_DESCRIPTION_MAX_LENGTH = 600;
    private const SHORT_MAX_LENGTH = 180;

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

    public function handle(
        TranslationService $translationService,
        OllamaRefinementService $ollamaRefinementService
    ): void {
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
        $templateMinLength = max(0, (int) config('events.description_template_min_length', 40));

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

            $finalDescription = $translatedDescription;
            $finalShort = $this->resolveInitialShort($candidate, $translatedDescription, $translatedTitle);
            $usedTemplateFallback = $this->shouldGenerateTemplateDescription($originalDescription, $templateMinLength);

            if ($usedTemplateFallback) {
                $template = $this->buildDeterministicSkTemplate($candidate, $translatedTitle);
                $finalDescription = $template['description'];
                $finalShort = $template['short'];
            }

            if ($this->isDescriptionRefinementEnabled()) {
                try {
                    $refined = $ollamaRefinementService->refine(
                        originalEnglishTitle: $originalTitle,
                        originalEnglishDescription: $originalDescription !== null ? (string) $originalDescription : null,
                        translatedTitle: $translatedTitle,
                        translatedDescription: $finalDescription
                    );

                    $translatedTitle = (string) ($refined['refined_title'] ?? $translatedTitle);
                    $refinedDescription = $this->sanitizeRefinedDescription($refined['refined_description'] ?? null);
                    if ($refinedDescription !== null) {
                        $finalDescription = $refinedDescription;

                        if (! $usedTemplateFallback) {
                            $finalShort = $this->buildShort($finalDescription, $translatedTitle);
                        }
                    }

                    if ((bool) ($refined['used_fallback'] ?? false)) {
                        Log::warning('Event candidate refinement fallback used; keeping base text where needed.', [
                            'event_candidate_id' => $candidate->id,
                        ]);
                    }
                } catch (\Throwable $exception) {
                    Log::warning('Event candidate refinement failed unexpectedly; keeping fallback/template text.', [
                        'event_candidate_id' => $candidate->id,
                        'message' => $exception->getMessage(),
                    ]);
                }
            }

            $finalDescription = $this->sanitizeDescription($finalDescription);
            $finalShort = $this->sanitizeShort($finalShort);
            if ($finalShort === null) {
                $finalShort = $this->buildShort($finalDescription, $translatedTitle);
            }

            $candidate->update([
                'short' => $finalShort,
                'description' => $finalDescription,
                'translated_title' => $translatedTitle,
                'translated_description' => $finalDescription,
                'translation_status' => EventCandidate::TRANSLATION_DONE,
                'translation_error' => null,
                'translated_at' => now(),
            ]);

            Log::info('Event candidate translated', [
                'event_candidate_id' => $candidate->id,
                'force' => $this->force,
                'template_fallback_used' => $usedTemplateFallback,
            ]);
        } catch (TranslationServiceException $exception) {
            $template = $this->buildDeterministicSkTemplate($candidate, $originalTitle);

            $candidate->update([
                'short' => $this->sanitizeShort($template['short']) ?? $this->buildShort($template['description'], $originalTitle),
                'description' => $this->sanitizeDescription($template['description']) ?? $template['description'],
                'translated_title' => $originalTitle,
                'translated_description' => $this->sanitizeDescription($template['description']) ?? $template['description'],
                'translation_status' => EventCandidate::TRANSLATION_FAILED,
                'translation_error' => $exception->errorCode(),
                'translated_at' => now(),
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

    private function shouldGenerateTemplateDescription(?string $description, int $minLength): bool
    {
        if ($description === null) {
            return true;
        }

        return mb_strlen(trim($description), 'UTF-8') < $minLength;
    }

    /**
     * @return array{description:string,short:string}
     */
    private function buildDeterministicSkTemplate(EventCandidate $candidate, string $translatedTitle): array
    {
        $title = $this->sanitizeInline($translatedTitle) ?: $this->sanitizeInline((string) $candidate->title) ?: 'Astronomicka udalost';
        $date = $this->formatTemplateDate($candidate->max_at ?: $candidate->start_at);
        $zhr = $this->extractZhrFromRawPayload($candidate->raw_payload);

        if ($this->isMeteorShower($candidate)) {
            $activitySentence = $zhr !== null
                ? "Ocakavana aktivita je {$zhr} meteorov za hodinu."
                : 'Ocakavana aktivita je neznama.';

            return [
                'description' => "Meteoricky roj {$title} ma maximum priblizne {$date}. {$activitySentence} Viditelnost zavisi od pocasia a svetelneho znecistenia.",
                'short' => "Maximum meteorickeho roja {$title} je priblizne {$date}.",
            ];
        }

        return [
            'description' => "Astronomicka udalost {$title} nastane priblizne {$date}. Pozorovanie je mozne pri vhodnych podmienkach.",
            'short' => "{$title} priblizne {$date}.",
        ];
    }

    private function isMeteorShower(EventCandidate $candidate): bool
    {
        $haystack = Str::lower(implode(' ', array_filter([
            (string) $candidate->type,
            (string) $candidate->raw_type,
            (string) $candidate->title,
        ])));

        return str_contains($haystack, 'meteor');
    }

    private function formatTemplateDate(mixed $moment): string
    {
        if (! $moment instanceof CarbonInterface) {
            return 'v neurcenom case';
        }

        $timezone = (string) config('events.timezone', config('events.source_timezone', 'Europe/Bratislava'));
        return $moment->clone()->setTimezone($timezone)->format('d.m.Y');
    }

    private function extractZhrFromRawPayload(mixed $rawPayload): ?int
    {
        $payload = null;

        if (is_string($rawPayload) && trim($rawPayload) !== '') {
            $decoded = json_decode($rawPayload, true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        } elseif (is_array($rawPayload)) {
            $payload = $rawPayload;
        }

        if (! is_array($payload)) {
            return null;
        }

        $zhr = $payload['zhr'] ?? null;
        if ($zhr === null || ! is_numeric((string) $zhr)) {
            return null;
        }

        $value = (int) $zhr;
        return $value > 0 ? $value : null;
    }

    private function isDescriptionRefinementEnabled(): bool
    {
        return (bool) config(
            'events.refine_descriptions_with_ollama',
            config('ai.ollama_refinement_enabled', false)
        );
    }

    private function sanitizeRefinedDescription(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $sanitized = $this->sanitizeDescription($value);
        if ($sanitized === null) {
            return null;
        }

        if (mb_strlen($sanitized, 'UTF-8') > self::REFINED_DESCRIPTION_MAX_LENGTH) {
            return null;
        }

        return $sanitized;
    }

    private function resolveInitialShort(
        EventCandidate $candidate,
        ?string $translatedDescription,
        string $translatedTitle
    ): ?string {
        $candidateShort = $this->sanitizeShort((string) ($candidate->short ?? ''));
        if ($candidateShort !== null) {
            return $candidateShort;
        }

        return $this->buildShort($translatedDescription, $translatedTitle);
    }

    private function buildShort(?string $description, string $translatedTitle): string
    {
        $descriptionShort = $this->sanitizeShort($description);
        if ($descriptionShort !== null) {
            return Str::limit($descriptionShort, self::SHORT_MAX_LENGTH, '');
        }

        $title = $this->sanitizeInline($translatedTitle);
        if ($title === '') {
            $title = 'Astronomicka udalost';
        }

        return Str::limit($title, self::SHORT_MAX_LENGTH, '');
    }

    private function sanitizeDescription(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim(strip_tags($value));
        if ($normalized === '') {
            return null;
        }

        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;
        $normalized = trim($normalized);

        return $normalized !== '' ? $normalized : null;
    }

    private function sanitizeShort(?string $value): ?string
    {
        $sanitized = $this->sanitizeDescription($value);
        if ($sanitized === null) {
            return null;
        }

        return Str::limit($sanitized, self::SHORT_MAX_LENGTH, '');
    }

    private function sanitizeInline(string $value): string
    {
        $normalized = trim(strip_tags($value));
        if ($normalized === '') {
            return '';
        }

        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;
        return trim($normalized);
    }
}
