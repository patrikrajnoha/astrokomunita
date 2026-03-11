<?php

namespace App\Jobs;

use App\Models\EventCandidate;
use App\Services\AI\OllamaRefinementService;
use App\Services\Bots\Contracts\BotTranslationServiceInterface;
use App\Services\Bots\Exceptions\BotTranslationException;
use App\Services\Translation\AstronomyPhraseNormalizer;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TranslateEventCandidateJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private const REFINED_DESCRIPTION_MAX_LENGTH = 600;

    private const SHORT_MAX_LENGTH = 180;

    /**
     * @var array<int,string>
     */
    private const EN_SHORT_HINT_TOKENS = [
        'the',
        'and',
        'with',
        'for',
        'from',
        'this',
        'that',
        'are',
        'was',
        'were',
        'best',
        'known',
        'producing',
        'years',
        'when',
        'activity',
        'maximum',
        'minimum',
        'lunar',
        'phase',
        'meteor',
        'shower',
        'visible',
        'visibility',
    ];

    public int $tries = 4;

    public int $uniqueFor = 600;

    public function __construct(
        public readonly int $candidateId,
        public readonly bool $force = false,
    ) {}

    /**
     * @return array<int,int>
     */
    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function uniqueId(): string
    {
        return 'event-candidate-translation-'.$this->candidateId.'-'.($this->force ? 'force' : 'normal');
    }

    public function handle(
        BotTranslationServiceInterface $translationService,
        OllamaRefinementService $ollamaRefinementService,
        AstronomyPhraseNormalizer $phraseNormalizer,
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
            $translationResult = $translationService->translate(
                $originalTitle,
                $originalDescription !== null ? (string) $originalDescription : null,
                'sk'
            );
            $qualityFlags = $this->extractTranslationQualityFlags($translationResult);
            $forceTemplateFromQuality = $this->shouldForceTemplateFromQualityFlags($qualityFlags);

            $translatedTitle = trim((string) ($translationResult['translated_title'] ?? ''));
            if ($translatedTitle === '') {
                $translatedTitle = $originalTitle;
            }
            $translatedTitle = $this->resolveTitleWithQualityGate(
                translatedTitle: $translatedTitle,
                originalTitle: $originalTitle,
                phraseNormalizer: $phraseNormalizer,
                candidateId: (int) $candidate->id,
                stage: 'initial'
            );

            $translatedDescriptionRaw = $translationResult['translated_content'] ?? null;
            $translatedDescription = is_string($translatedDescriptionRaw)
                ? trim($translatedDescriptionRaw)
                : null;
            if ($translatedDescription !== null) {
                $translatedDescription = $this->applyTerminologyMap($translatedDescription, $phraseNormalizer);
            }

            $finalDescription = $translatedDescription;
            $finalShort = $this->resolveInitialShort(
                $candidate,
                $translatedDescription,
                $translatedTitle,
                $phraseNormalizer
            );
            $usedTemplateFallback = $forceTemplateFromQuality
                || $this->shouldGenerateTemplateDescription($originalDescription, $templateMinLength);

            if ($usedTemplateFallback) {
                $template = $this->buildDeterministicSkTemplate($candidate, $translatedTitle);
                $finalDescription = $template['description'];
                $finalShort = $template['short'];
            }

            if ($forceTemplateFromQuality) {
                Log::warning('Event candidate template fallback forced by translation quality flags.', [
                    'event_candidate_id' => $candidate->id,
                    'quality_flags' => $qualityFlags,
                ]);
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
                    $translatedTitle = $this->resolveTitleWithQualityGate(
                        translatedTitle: $translatedTitle,
                        originalTitle: $originalTitle,
                        phraseNormalizer: $phraseNormalizer,
                        candidateId: (int) $candidate->id,
                        stage: 'refined'
                    );
                    $refinedDescription = $this->sanitizeRefinedDescription($refined['refined_description'] ?? null);
                    if ($refinedDescription !== null) {
                        $finalDescription = $this->applyTerminologyMap($refinedDescription, $phraseNormalizer);

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

            if (! $usedTemplateFallback && $this->shouldFallbackForDescriptionQuality($finalDescription, $phraseNormalizer)) {
                $template = $this->buildDeterministicSkTemplate($candidate, $translatedTitle);
                $finalDescription = $template['description'];
                $finalShort = $template['short'];
                $usedTemplateFallback = true;

                Log::warning('Event candidate template fallback forced by untranslated description quality gate.', [
                    'event_candidate_id' => $candidate->id,
                ]);
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
                'quality_flags' => $qualityFlags,
                'template_fallback_forced_by_quality' => $forceTemplateFromQuality,
            ]);
        } catch (\Throwable $exception) {
            $fallbackTitle = $this->resolveTitleWithQualityGate(
                translatedTitle: $originalTitle,
                originalTitle: $originalTitle,
                phraseNormalizer: $phraseNormalizer,
                candidateId: (int) $candidate->id,
                stage: 'translation_error'
            );
            $template = $this->buildDeterministicSkTemplate($candidate, $fallbackTitle);
            $resolvedErrorCode = $this->resolveErrorCode($exception);
            $isTranslationProviderFailure = $exception instanceof BotTranslationException;

            $candidate->update([
                'short' => $this->sanitizeShort($template['short']) ?? $this->buildShort($template['description'], $fallbackTitle),
                'description' => $this->sanitizeDescription($template['description']) ?? $template['description'],
                'translated_title' => $fallbackTitle,
                'translated_description' => $this->sanitizeDescription($template['description']) ?? $template['description'],
                'translation_status' => $isTranslationProviderFailure ? EventCandidate::TRANSLATION_DONE : EventCandidate::TRANSLATION_FAILED,
                'translation_error' => $isTranslationProviderFailure ? null : $resolvedErrorCode,
                'translated_at' => now(),
            ]);

            Log::warning('Event candidate translation failed', [
                'event_candidate_id' => $candidate->id,
                'error_code' => $resolvedErrorCode,
                'message' => $exception->getMessage(),
                'fallback_applied' => $isTranslationProviderFailure,
            ]);

            if (! $isTranslationProviderFailure) {
                throw $exception;
            }
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
     * @param  array<string,mixed>  $translationResult
     * @return array<int,string>
     */
    private function extractTranslationQualityFlags(array $translationResult): array
    {
        $meta = $translationResult['meta'] ?? null;
        if (! is_array($meta)) {
            return [];
        }

        $flags = $meta['quality_flags'] ?? null;
        if (! is_array($flags)) {
            return [];
        }

        $normalized = array_values(array_unique(array_filter(array_map(
            static fn (mixed $flag): string => strtolower(trim((string) $flag)),
            $flags
        ), static fn (string $flag): bool => $flag !== '')));

        return $normalized;
    }

    /**
     * @param  array<int,string>  $qualityFlags
     */
    private function shouldForceTemplateFromQualityFlags(array $qualityFlags): bool
    {
        if ($qualityFlags === []) {
            return false;
        }

        if (! (bool) config('events.translation.quality_gate.force_template_on_severe_flags', true)) {
            return false;
        }

        $severe = config('events.translation.quality_gate.severe_flags', []);
        if (! is_array($severe) || $severe === []) {
            return false;
        }

        $severeSet = array_values(array_unique(array_filter(array_map(
            static fn (mixed $flag): string => strtolower(trim((string) $flag)),
            $severe
        ), static fn (string $flag): bool => $flag !== '')));

        if ($severeSet === []) {
            return false;
        }

        return array_intersect($qualityFlags, $severeSet) !== [];
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
            $meteorName = $this->resolveMeteorShowerNameForTemplate($title);
            $activitySentence = $zhr !== null
                ? "Ocakavana aktivita je {$zhr} meteorov za hodinu."
                : 'Ocakavana aktivita je neznama.';

            return [
                'description' => "Meteoricky roj {$meteorName} ma maximum priblizne {$date}. {$activitySentence} Viditelnost zavisi od pocasia a svetelneho znecistenia.",
                'short' => "Maximum meteorickeho roja {$meteorName} je priblizne {$date}.",
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

    private function resolveMeteorShowerNameForTemplate(string $title): string
    {
        $name = $title;
        if (preg_match('/^meteorick(?:\x{00FD}|y)\s+roj\s+(.+)$/iu', $title, $matches) === 1) {
            $normalized = $this->sanitizeInline((string) ($matches[1] ?? ''));
            if ($normalized !== '') {
                $name = $normalized;
            }
        }

        $name = preg_replace('/\bJuzne\s+Tauridy\b/u', 'Juznych Taurid', $name) ?? $name;
        $name = preg_replace('/\bSeverne\s+Tauridy\b/u', 'Severnych Taurid', $name) ?? $name;
        $name = preg_replace('/\b([\pL][\pL\-]*(?:\s+[\pL][\pL\-]*)?)idy\b/u', '$1id', $name) ?? $name;

        return $this->sanitizeInline($name) ?: $title;
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
        string $translatedTitle,
        AstronomyPhraseNormalizer $phraseNormalizer
    ): ?string {
        $candidateShort = $this->sanitizeShort((string) ($candidate->short ?? ''));
        if ($candidateShort !== null) {
            $candidateShort = $this->applyTerminologyMap($candidateShort, $phraseNormalizer);
            if (! $this->isLikelyUntranslatedShort($candidateShort, $phraseNormalizer)) {
                return $candidateShort;
            }
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

    private function isLikelyUntranslatedShort(string $value, AstronomyPhraseNormalizer $phraseNormalizer): bool
    {
        if ($value === '') {
            return false;
        }

        if ($phraseNormalizer->hasResidualEnglishTokens($value, 'sk')) {
            return true;
        }

        return $this->countKnownEnglishTokens($value) >= 2;
    }

    private function shouldFallbackForDescriptionQuality(?string $value, AstronomyPhraseNormalizer $phraseNormalizer): bool
    {
        $description = $this->sanitizeDescription($value);
        if ($description === null) {
            return true;
        }

        if ($phraseNormalizer->hasResidualEnglishTokens($description, 'sk')) {
            return true;
        }

        return $this->countKnownEnglishTokens($description) >= 5;
    }

    private function countKnownEnglishTokens(string $value): int
    {
        $matches = [];
        preg_match_all('/\b[a-z]{2,}\b/i', $value, $matches);
        $tokens = $matches[0] ?? [];
        if ($tokens === []) {
            return 0;
        }

        $knownTokens = array_flip(self::EN_SHORT_HINT_TOKENS);
        $count = 0;
        foreach ($tokens as $token) {
            $normalized = strtolower(trim((string) $token));
            if ($normalized === '' || ! isset($knownTokens[$normalized])) {
                continue;
            }
            $count++;
        }

        return $count;
    }

    private function resolveErrorCode(\Throwable $exception): string
    {
        if ($exception instanceof BotTranslationException) {
            $message = strtolower($exception->getMessage());
            if (str_contains($message, 'timeout')) {
                return 'translation_timeout';
            }

            return 'translation_error';
        }

        return 'translation_error';
    }

    private function applyTerminologyMap(string $text, AstronomyPhraseNormalizer $phraseNormalizer): string
    {
        return $phraseNormalizer->normalize($text, 'sk');
    }

    private function resolveTitleWithQualityGate(
        string $translatedTitle,
        string $originalTitle,
        AstronomyPhraseNormalizer $phraseNormalizer,
        int $candidateId,
        string $stage
    ): string {
        $resolution = $phraseNormalizer->normalizeTitleWithFallback($translatedTitle, $originalTitle, 'sk');
        $title = $this->sanitizeInline((string) ($resolution['title'] ?? ''));
        if ($title === '') {
            $title = 'Astronomicka udalost';
        }

        if ((bool) ($resolution['used_fallback'] ?? false)) {
            Log::warning('Event candidate title quality gate fallback used.', [
                'event_candidate_id' => $candidateId,
                'stage' => $stage,
                'reason' => (string) ($resolution['reason'] ?? 'unknown'),
            ]);
        }

        return $title;
    }
}
