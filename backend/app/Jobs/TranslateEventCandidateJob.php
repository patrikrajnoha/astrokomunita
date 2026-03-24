<?php

namespace App\Jobs;

use App\Models\Event;
use App\Models\EventCandidate;
use App\Services\AI\OllamaRefinementService;
use App\Services\Bots\Contracts\BotTranslationServiceInterface;
use App\Services\Bots\Exceptions\BotTranslationException;
use App\Services\Events\EventDescriptionOriginRecorder;
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

    private const GENERIC_EVENT_TITLE = "Astronomick\u{00E1} udalos\u{0165}";
    private const REQUESTED_MODE_AI = 'ai';
    private const REQUESTED_MODE_TEMPLATE = 'template';

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

    /**
     * @var array<int,string>
     */
    private const CELESTIAL_TERMS = [
        'sun',
        'moon',
        'mercury',
        'venus',
        'mars',
        'jupiter',
        'saturn',
        'uranus',
        'neptune',
        'pluto',
        'slnko',
        'mesiac',
        'merkur',
        'mars',
        'jupiter',
        'saturn',
        'uran',
        'neptun',
        'pluto',
        'regulus',
        'spica',
        'antares',
        'pollux',
        'pleiades',
        'plejady',
    ];

    public int $tries = 4;

    public int $uniqueFor = 600;

    public function __construct(
        public readonly int $candidateId,
        public readonly bool $force = false,
        public readonly ?string $requestedMode = null,
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
        return 'event-candidate-translation-'.$this->candidateId.'-'.($this->force ? 'force' : 'normal').'-'.$this->resolveRequestedMode();
    }

    public function handle(
        BotTranslationServiceInterface $translationService,
        OllamaRefinementService $ollamaRefinementService,
        AstronomyPhraseNormalizer $phraseNormalizer,
        ?EventDescriptionOriginRecorder $originRecorder = null,
    ): void {
        $candidate = EventCandidate::query()->find($this->candidateId);
        if (! $candidate) {
            return;
        }
        $originRecorder ??= app(EventDescriptionOriginRecorder::class);

        $requestedMode = $this->resolveRequestedMode();
        $explicitAiRequested = $this->isExplicitAiModeRequest();
        $explicitTemplateRequested = $requestedMode === self::REQUESTED_MODE_TEMPLATE;

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

        $existingTranslatedTitle = filled($candidate->translated_title) ? (string) $candidate->translated_title : null;

        $needsPendingSync = (string) ($candidate->original_title ?? '') !== $originalTitle
            || (string) ($candidate->original_description ?? '') !== (string) ($originalDescription ?? '')
            || (string) ($candidate->translation_status ?? '') !== EventCandidate::TRANSLATION_PENDING
            || filled($candidate->translation_error);

        if ($needsPendingSync) {
            $candidate->update([
                'original_title' => $originalTitle,
                'original_description' => $originalDescription,
                'translation_status' => EventCandidate::TRANSLATION_PENDING,
                'translation_mode' => null,
                'translation_error' => null,
            ]);
        }

        if ($explicitTemplateRequested) {
            $templateBaseTitle = trim((string) ($candidate->translated_title ?? ''));
            if ($templateBaseTitle === '') {
                $templateBaseTitle = $originalTitle;
            }
            $templateBaseTitle = $this->resolveTitleWithQualityGate(
                translatedTitle: $templateBaseTitle,
                originalTitle: $originalTitle,
                phraseNormalizer: $phraseNormalizer,
                candidateId: (int) $candidate->id,
                stage: 'template'
            );

            $template = $this->buildDeterministicSkTemplate($candidate, $templateBaseTitle);
            $templateDescription = $this->sanitizeDescription($template['description']) ?? $template['description'];
            $templateShort = $this->sanitizeShort($template['short']) ?? $this->buildShort($templateDescription, $templateBaseTitle);

            $candidate->update([
                'short' => $templateShort,
                'description' => $templateDescription,
                'translated_title' => $templateBaseTitle,
                'translated_description' => $templateDescription,
                'translation_status' => EventCandidate::TRANSLATION_DONE,
                'translation_mode' => EventCandidate::TRANSLATION_MODE_TEMPLATE,
                'translation_error' => null,
                'translated_at' => now(),
            ]);
            $candidate->refresh();
            $this->syncPublishedEventFromCandidate(
                $candidate,
                $originRecorder,
                $requestedMode,
                EventCandidate::TRANSLATION_MODE_TEMPLATE
            );

            Log::info('Event candidate translated', [
                'event_candidate_id' => $candidate->id,
                'force' => $this->force,
                'requested_mode' => $requestedMode,
                'translation_mode' => EventCandidate::TRANSLATION_MODE_TEMPLATE,
            ]);

            return;
        }

        try {
            // For explicit AI requests with an existing translation: reuse the stored
            // translated title instead of calling LibreTranslate again.
            if ($explicitAiRequested && $existingTranslatedTitle !== null) {
                $translationResult = [
                    'translated_title'   => $existingTranslatedTitle,
                    'translated_content' => $candidate->translated_description,
                    'quality_flags'      => [],
                ];
            } else {
                // For explicit AI requests: if LibreTranslate fails, fall back to original English
                // text and let Ollama handle translation+refinement in one step.
                try {
                    $translationResult = $translationService->translate(
                        $originalTitle,
                        null,
                        'sk'
                    );
                } catch (\Throwable $translationException) {
                    if (! $explicitAiRequested) {
                        throw $translationException;
                    }
                    Log::info('Event candidate LibreTranslate failed for explicit AI request; using original English as base.', [
                        'event_candidate_id' => $candidate->id,
                        'message' => $translationException->getMessage(),
                    ]);
                    $translationResult = [
                        'translated_title'   => $originalTitle,
                        'translated_content' => $originalDescription,
                        'quality_flags'      => [],
                    ];
                }
            }

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

            $template = $this->buildDeterministicSkTemplate($candidate, $translatedTitle);
            $finalDescription = $template['description'];
            $finalShort = $template['short'];
            $titleRefined = false;
            $descriptionRefined = false;
            $usedTemplateFallback = true;

            if ($this->shouldRunRefinement($usedTemplateFallback, $requestedMode)) {
                try {
                    $titleBeforeRefinement = $translatedTitle;
                    $refined = $ollamaRefinementService->refine(
                        originalEnglishTitle: $originalTitle,
                        originalEnglishDescription: $originalDescription !== null ? (string) $originalDescription : null,
                        translatedTitle: $translatedTitle,
                        translatedDescription: $finalDescription,
                        forceRun: $explicitAiRequested,
                    );

                    $titleUsedFallback = (bool) ($refined['title_used_fallback'] ?? ($refined['used_fallback'] ?? false));
                    $descriptionUsedFallback = (bool) ($refined['description_used_fallback'] ?? ($refined['used_fallback'] ?? false));

                    if (! $titleUsedFallback) {
                        $translatedTitle = (string) ($refined['refined_title'] ?? $translatedTitle);
                        $translatedTitle = $this->resolveTitleWithQualityGate(
                            translatedTitle: $translatedTitle,
                            originalTitle: $originalTitle,
                            phraseNormalizer: $phraseNormalizer,
                            candidateId: (int) $candidate->id,
                            stage: 'refined'
                        );
                        if ($translatedTitle !== '' && $translatedTitle !== $titleBeforeRefinement) {
                            $titleRefined = true;
                        }
                    }

                    if (! $descriptionUsedFallback) {
                        $refinedDescription = $this->sanitizeRefinedDescription($refined['refined_description'] ?? null);
                        if ($refinedDescription !== null) {
                            $finalDescription = $this->applyTerminologyMap($refinedDescription, $phraseNormalizer);
                            $descriptionRefined = true;
                        }
                    }

                    if ($titleUsedFallback || $descriptionUsedFallback) {
                        Log::warning('Event candidate refinement fallback used; keeping base text where needed.', [
                            'event_candidate_id' => $candidate->id,
                            'title_fallback' => $titleUsedFallback,
                            'description_fallback' => $descriptionUsedFallback,
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

            $translationMode = match(true) {
                $titleRefined && $descriptionRefined => EventCandidate::TRANSLATION_MODE_AI_REFINED,
                $titleRefined                        => EventCandidate::TRANSLATION_MODE_AI_TITLE,
                $descriptionRefined                  => EventCandidate::TRANSLATION_MODE_AI_DESCRIPTION,
                default                              => EventCandidate::TRANSLATION_MODE_TEMPLATE,
            };

            $candidate->update([
                'short' => $finalShort,
                'description' => $finalDescription,
                'translated_title' => $translatedTitle,
                'translated_description' => $finalDescription,
                'translation_status' => EventCandidate::TRANSLATION_DONE,
                'translation_mode' => $translationMode,
                'translation_error' => null,
                'translated_at' => now(),
            ]);
            $candidate->refresh();
            $this->syncPublishedEventFromCandidate(
                $candidate,
                $originRecorder,
                $requestedMode,
                $translationMode
            );

            Log::info('Event candidate translated', [
                'event_candidate_id' => $candidate->id,
                'force' => $this->force,
                'requested_mode' => $requestedMode,
                'translation_mode' => $translationMode,
            ]);
        } catch (\Throwable $exception) {
            $resolvedErrorCode = $this->resolveErrorCode($exception);
            $isExplicitAiRequested = $this->isExplicitAiModeRequest();

            if ($isExplicitAiRequested) {
                $candidate->update([
                    'translation_status' => EventCandidate::TRANSLATION_FAILED,
                    'translation_mode' => null,
                    'translation_error' => $resolvedErrorCode,
                    'translated_at' => null,
                ]);

                Log::warning('Event candidate translation failed', [
                    'event_candidate_id' => $candidate->id,
                    'error_code' => $resolvedErrorCode,
                    'message' => $exception->getMessage(),
                    'fallback_applied' => false,
                    'requested_mode' => self::REQUESTED_MODE_AI,
                ]);

                throw $exception;
            }

            $fallbackTitle = $this->resolveTitleWithQualityGate(
                translatedTitle: $originalTitle,
                originalTitle: $originalTitle,
                phraseNormalizer: $phraseNormalizer,
                candidateId: (int) $candidate->id,
                stage: 'translation_error'
            );
            $template = $this->buildDeterministicSkTemplate($candidate, $fallbackTitle);
            $isTranslationProviderFailure = $exception instanceof BotTranslationException;

            $candidate->update([
                'short' => $this->sanitizeShort($template['short']) ?? $this->buildShort($template['description'], $fallbackTitle),
                'description' => $this->sanitizeDescription($template['description']) ?? $template['description'],
                'translated_title' => $fallbackTitle,
                'translated_description' => $this->sanitizeDescription($template['description']) ?? $template['description'],
                'translation_status' => $isTranslationProviderFailure ? EventCandidate::TRANSLATION_DONE : EventCandidate::TRANSLATION_FAILED,
                'translation_mode' => EventCandidate::TRANSLATION_MODE_TEMPLATE,
                'translation_error' => $isTranslationProviderFailure ? null : $resolvedErrorCode,
                'translated_at' => now(),
            ]);
            $candidate->refresh();
            if ($isTranslationProviderFailure) {
                $this->syncPublishedEventFromCandidate(
                    $candidate,
                    $originRecorder,
                    $requestedMode,
                    EventCandidate::TRANSLATION_MODE_TEMPLATE
                );
            }

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

    /**
     * @return array{description:string,short:string}
     */
    private function buildDeterministicSkTemplate(EventCandidate $candidate, string $translatedTitle): array
    {
        $title = $this->sanitizeInline($translatedTitle)
            ?: $this->sanitizeInline((string) $candidate->title)
            ?: self::GENERIC_EVENT_TITLE;
        $date = $this->formatTemplateDate($candidate->max_at ?: $candidate->start_at);
        $zhr = $this->extractZhrFromRawPayload($candidate->raw_payload);
        $v = (int) $candidate->id % 3;
        $type = Str::lower(trim((string) ($candidate->type ?? '')));

        if ($this->isMeteorShower($candidate)) {
            $meteorName = $this->resolveMeteorShowerNameForTemplate($title);
            $activity = $zhr !== null
                ? "O\u{010D}ak\u{00E1}van\u{00E1} aktivita dosahuje a\u{017E} {$zhr} meteorov za hodinu."
                : null;
            $variants = [
                [
                    'description' => "Meteorick\u{00FD} roj {$meteorName} m\u{00E1} maximum pribli\u{017E}ne {$date}. ".($activity ?? "Aktivita roja b\u{00FD}va premenliv\u{00E1}.")." Pozorovanie je najlep\u{0161}ie z tmav\u{00E9}ho miesta vo\u{013E}n\u{00FD}m okom.",
                    'short' => "Maximum meteorick\u{00E9}ho roja {$meteorName} je pribli\u{017E}ne {$date}.",
                ],
                [
                    'description' => "V noci okolo {$date} vrchol\u{00ED} meteorick\u{00FD} roj {$meteorName}. ".($activity ?? "Po\u{010D}et meteor\u{00F3}v z\u{00E1}vis\u{00ED} od aktu\u{00E1}lnej aktivity roja.")." Na pozorovanie sta\u{010D}\u{00ED} vo\u{013E}n\u{00E9} oko a tmav\u{00E1} obloha \u{010F}aleko od mest.",
                    'short' => "Meteorick\u{00FD} roj {$meteorName} vrchol\u{00ED} okolo {$date}.",
                ],
                [
                    'description' => "{$meteorName} s\u{00FA} meteorick\u{00FD} roj s maximom pribli\u{017E}ne {$date}. ".($activity ?? "Intenzita m\u{00F4}\u{017E}e by\u{0165} rozdieln\u{00E1} ka\u{017E}d\u{00FD} rok.")." Tmav\u{00E9} miesto bez sveteln\u{00E9}ho zne\u{010D}istenia v\u{00FD}razne zlep\u{0161}\u{00ED} z\u{00E1}\u{017E}itok.",
                    'short' => "{$meteorName} — maximum pribli\u{017E}ne {$date}.",
                ],
            ];
            return $variants[$v];
        }

        if ($type === 'eclipse_lunar') {
            $variants = [
                [
                    'description' => "Zatmenie Mesiaca nastane pribli\u{017E}ne {$date}. Mesiac vstúpi do tieňa Zeme a môže získať charakteristickú červenú farbu. Pozorovanie je možné voľným okom z celej nočnej polokoule.",
                    'short' => "Zatmenie Mesiaca pribli\u{017E}ne {$date}.",
                ],
                [
                    'description' => "Pribli\u{017E}ne {$date} bude pozorovate\u{013E}n\u{00E9} zatmenie Mesiaca. Jav nastáva, keď Zem vstúpi medzi Slnko a Mesiac. Nevyžaduje špeciálne vybavenie — sta\u{010D}\u{00ED} vo\u{013E}n\u{00E9} oko.",
                    'short' => "Zatmenie Mesiaca pribli\u{017E}ne {$date}.",
                ],
                [
                    'description' => "Okolo {$date} nastane zatmenie Mesiaca. Zemský tieň na Mesiaci vytvára pozoruhodný astronomický jav viditeľný bez akéhokoľvek vybavenia. Najlepší výhľad je z miesta s otvoreným horizontom.",
                    'short' => "Zatmenie Mesiaca okolo {$date}.",
                ],
            ];
            return $variants[$v];
        }

        if ($type === 'eclipse_solar') {
            $variants = [
                [
                    'description' => "Zatmenie Slnka nastane pribli\u{017E}ne {$date}. Mesiac sa postaví medzi Zem a Slnko a čiastočne alebo úplne zakryje slnečný disk. Pri pozorovaní je nevyhnutné používať certifikované solárne okuliare alebo filter.",
                    'short' => "Zatmenie Slnka pribli\u{017E}ne {$date}.",
                ],
                [
                    'description' => "Pribli\u{017E}ne {$date} nastane zatmenie Slnka. Nikdy nehľaďte na Slnko priamo — pozorovanie vyžaduje špeciálny solárny filter alebo certifikované okuliare. Viditeľnosť závisí od polohy pozorovateľa.",
                    'short' => "Zatmenie Slnka pribli\u{017E}ne {$date}.",
                ],
                [
                    'description' => "Okolo {$date} bude viditeľné zatmenie Slnka. Ide o jav, pri ktorom Mesiac prechádza pred slnečným diskom. Priame pozorovanie bez filtra je nebezpečné pre zrak.",
                    'short' => "Zatmenie Slnka okolo {$date}.",
                ],
            ];
            return $variants[$v];
        }

        if ($type === 'aurora') {
            $variants = [
                [
                    'description' => "Podmienky pre výskyt polárnej žiary sú priaznivé okolo {$date}. Jav je spôsobený nabitými časticami slnečného vetra interagujúcimi so zemskou atmosférou. Najlepšie vyhliadky sú z tmavých miest ďalej od mestských svetiel.",
                    'short' => "Priaznivé podmienky pre polárnu žiaru okolo {$date}.",
                ],
                [
                    'description' => "Okolo {$date} môže byť viditeľná polárna žiara. Viditeľnosť závisí od aktuálnej geomagnetickej aktivity a miestnej oblačnosti. Sledujte aktuálne predpovede geomagnetickej búrky.",
                    'short' => "Polárna žiara možná okolo {$date}.",
                ],
                [
                    'description' => "Polárna žiara môže byť aktívna pribli\u{017E}ne {$date}. Tento jav vzniká v zemskej atmosfére vo výške desiatky až stovky kilometrov. Čím tmavšie miesto pozorovania, tým väčšia šanca na výrazný jav.",
                    'short' => "Polárna žiara možná pribli\u{017E}ne {$date}.",
                ],
            ];
            return $variants[$v];
        }

        // Title-based detection for short AstroPixels events (type = "other" / "Iná udalosť")
        $rawTitle = Str::lower(trim((string) ($candidate->original_title ?: $candidate->title ?: '')));

        if (str_contains($rawTitle, 'apogee')) {
            $km = $this->extractFormattedKm($rawTitle);
            $d = $km !== null ? " vo vzdialenosti {$km} km od Zeme" : '';
            $variants = [
                ['description' => "Mesiac dosiahne apogeum — najvzdialenejší bod svojej obežnej dráhy okolo Zeme{$d}. V tejto polohe má menší uhlový priemer než pri perigeu.", 'short' => "Mesiac v apogeu{$d}."],
                ['description' => "Apogeum je bod dráhy, v ktorom je Mesiac od Zeme najďalej{$d}. V porovnaní s perigeom sa javí o niečo menší a menej jasný.", 'short' => "Mesiac dosahuje apogeum{$d}."],
                ['description' => "Mesiac prechádza apogeom{$d}. Ide o pravidelne sa opakujúcu fázu obehu, pri ktorej je geocentrická vzdialenosť Mesiaca maximálna.", 'short' => "Mesiac v apogeu{$d}."],
            ];
            return $variants[$v];
        }

        if (str_contains($rawTitle, 'perigee')) {
            $km = $this->extractFormattedKm($rawTitle);
            $d = $km !== null ? " vo vzdialenosti {$km} km od Zeme" : '';
            $variants = [
                ['description' => "Mesiac dosiahne perigeum — najbližší bod svojej obežnej dráhy k Zemi{$d}. V tejto polohe má väčší uhlový priemer než pri apogeu.", 'short' => "Mesiac v perigeu{$d}."],
                ['description' => "Perigeum je bod dráhy, v ktorom je Mesiac k Zemi najbližšie{$d}. V porovnaní s apogeom sa javí o niečo väčší a jasnejší.", 'short' => "Mesiac dosahuje perigeum{$d}."],
                ['description' => "Mesiac prechádza perigeom{$d}. Ide o pravidelne sa opakujúcu fázu obehu, pri ktorej je geocentrická vzdialenosť Mesiaca minimálna.", 'short' => "Mesiac v perigeu{$d}."],
            ];
            return $variants[$v];
        }

        if (preg_match('/\bnew\s+moon\b/i', $rawTitle) === 1) {
            $variants = [
                ['description' => "Nov Mesiaca nastane približne {$date}. V tejto fáze je Mesiac v konjunkcii so Slnkom a jeho osvetlená pologuľa je obrátená od Zeme, preto je na nočnej oblohe prakticky neviditeľný.", 'short' => "Nov Mesiaca približne {$date}."],
                ['description' => "Približne {$date} nastane nov Mesiaca. Keďže Mesiac sa nachádza medzi Zemou a Slnkom, jeho disk na nočnej oblohe nepozorujeme. Ide o vhodné obdobie na pozorovanie hmlovín, galaxií a hviezdokôp.", 'short' => "Nov Mesiaca približne {$date}."],
                ['description' => "Nov Mesiaca približne {$date} prináša minimálny mesačný jas na oblohe. Vďaka tomu sa zvyšuje kontrast slabých objektov hlbokého vesmíru, najmä mimo svetelného znečistenia.", 'short' => "Nov Mesiaca približne {$date}."],
            ];
            return $variants[$v];
        }

        if (preg_match('/\bfull\s+moon\b/i', $rawTitle) === 1) {
            $variants = [
                ['description' => "Spln Mesiaca nastane približne {$date}. Osvetlená je celá privrátená pologuľa Mesiaca, čo výrazne zvyšuje jas nočnej oblohy.", 'short' => "Spln Mesiaca približne {$date}."],
                ['description' => "Približne {$date} nastane spln Mesiaca. Mesiac vychádza pri západe Slnka a zapadá pri jeho východe, takže je pozorovateľný počas väčšiny noci.", 'short' => "Spln Mesiaca približne {$date}."],
                ['description' => "Mesiac bude v splne približne {$date}. Silný mesačný jas znižuje kontrast slabých objektov hlbokého vesmíru, no zlepšuje podmienky na pozorovanie mesačného disku.", 'short' => "Spln Mesiaca približne {$date}."],
            ];
            return $variants[$v];
        }

        if (preg_match('/\bfirst\s+quarter\b/i', $rawTitle) === 1) {
            $variants = [
                ['description' => "Prvá štvrť Mesiaca nastane pribli\u{017E}ne {$date}. Mesiac bude viditeľný ako osvetlená pravá polovica od poludnia až do polnoci. Najviac detailov vynikne na terminátore (hranici medzi svetlom a tieňom).", 'short' => "Prvá štvrť Mesiaca pribli\u{017E}ne {$date}."],
                ['description' => "Pribli\u{017E}ne {$date} nastane prvá štvrť Mesiaca. Viditeľná je pravá osvetlená polovica mesačného disku. Na hranici osvetlenej a tieňovej časti sú krátery plasticky zvýraznené.", 'short' => "Mesiac v prvej štvrti pribli\u{017E}ne {$date}."],
                ['description' => "Mesiac v prvej štvrti je viditeľný od odpoludnia do polnoci. Táto fáza je obľúbená medzi pozorovateľmi, pretože terminátor (hranica medzi svetlom a tieňom) vytvára krásny plastický efekt na povrchu Mesiaca.", 'short' => "Prvá štvrť Mesiaca pribli\u{017E}ne {$date}."],
            ];
            return $variants[$v];
        }

        if (preg_match('/\b(?:last|third)\s+quarter\b/i', $rawTitle) === 1) {
            $variants = [
                ['description' => "Posledná štvrť Mesiaca nastane pribli\u{017E}ne {$date}. Mesiac vychádza okolo polnoci a viditeľný je ako osvetlená ľavá polovica ráno na oblohe. Krátery na terminátore (hranici medzi svetlom a tieňom) sú dobre viditeľné ďalekohľadom.", 'short' => "Posledná štvrť Mesiaca pribli\u{017E}ne {$date}."],
                ['description' => "Pribli\u{017E}ne {$date} nastane posledná štvrť Mesiaca. Viditeľná je ľavá osvetlená polovica mesačného disku, pozorovateľná od polnoci do poludnia. Ďalekohľad odhalí najviac detailov na terminátore (hranici medzi svetlom a tieňom).", 'short' => "Mesiac v poslednej štvrti pribli\u{017E}ne {$date}."],
                ['description' => "Mesiac v poslednej štvrti je viditeľný od polnoci do neskorého rána. Terminátor (hranica medzi svetlom a tieňom) na ľavej strane mesačného disku ponúka výborné podmienky na pozorovanie kráterov a pohorí.", 'short' => "Posledná štvrť Mesiaca pribli\u{017E}ne {$date}."],
            ];
            return $variants[$v];
        }

        if (preg_match('/\bequinox\b/i', $rawTitle) === 1) {
            $isVernal = preg_match('/\b(?:vernal|spring|march)\b/i', $rawTitle) === 1;
            $nazov = $isVernal ? 'Jarná rovnodennosť' : 'Jesenná rovnodennosť';
            $variants = [
                ['description' => "{$nazov} nastane pribli\u{017E}ne {$date}. Deň a noc sú v tento čas rovnako dlhé na celej Zemi. Rovnodennosť označuje astronomický prechod medzi dvoma ročnými obdobiami.", 'short' => "{$nazov} pribli\u{017E}ne {$date}."],
                ['description' => "Pribli\u{017E}ne {$date} nastane rovnodennosť — deň má rovnaký počet hodín ako noc. Tento astronomický míľnik označuje zmenu ročného obdobia.", 'short' => "{$nazov} pribli\u{017E}ne {$date}."],
                ['description' => "{$nazov} nastane okolo {$date}. V tento deň sú deň a noc rovnako dlhé. Ide o astronomický začiatok " . ($isVernal ? 'jari' : 'jesene') . ", keď Slnko prechádza cez nebeský rovník.", 'short' => "{$nazov} okolo {$date}."],
            ];
            return $variants[$v];
        }

        if (preg_match('/\bsolstice\b/i', $rawTitle) === 1) {
            $isSummer = preg_match('/\b(?:summer|june)\b/i', $rawTitle) === 1;
            $nazov = $isSummer ? 'Letný slnovrat' : 'Zimný slnovrat';
            $variants = [
                ['description' => "{$nazov} nastane pribli\u{017E}ne {$date}. " . ($isSummer ? 'Ide o najdlhší deň v roku.' : 'Ide o najkratší deň v roku.') . ' Slnovrat označuje astronomický začiatok ' . ($isSummer ? 'leta' : 'zimy') . '.', 'short' => "{$nazov} pribli\u{017E}ne {$date}."],
                ['description' => "Pribli\u{017E}ne {$date} nastane " . ($isSummer ? 'letný' : 'zimný') . ' slnovrat. ' . ($isSummer ? 'Deň je najdlhší v roku a noc najkratšia.' : 'Noc je najdlhšia v roku a deň najkratší.') . ' Slnko dosahuje ' . ($isSummer ? 'maximálnu' : 'minimálnu') . ' výšku nad obzorom.', 'short' => "{$nazov} pribli\u{017E}ne {$date}."],
                ['description' => "{$nazov} okolo {$date} — " . ($isSummer ? 'najdlhší deň roka označuje astronomický začiatok leta.' : 'najkratší deň roka označuje astronomický začiatok zimy.') . ' Jav súvisí s náklonom zemskej osi.', 'short' => "{$nazov} okolo {$date}."],
            ];
            return $variants[$v];
        }

        $conjData = $this->extractConjunctionData($rawTitle, $title);
        if ($conjData !== null) {
            ['body' => $body, 'deg' => $deg, 'isMoon' => $isMoon, 'target' => $target] = $conjData;
            if ($isMoon) {
                $variants = [
                    ['description' => "Konjunkcia {$body} s Mesiacom nastane pribli\u{017E}ne {$date}. Uhlov\u{00E1} vzdialenos\u{0165} objektov bude pribli\u{017E}ne {$deg}\u{00B0}, ide teda o zdanliv\u{00E9} pribl\u{00ED}\u{017E}enie pri poh\u{013E}ade zo Zeme.", 'short' => "Konjunkcia {$body} s Mesiacom ({$deg}\u{00B0}) pribli\u{017E}ne {$date}."],
                    ['description' => "Pribli\u{017E}ne {$date} nastane konjunkcia {$body} s Mesiacom. Obe teles\u{00E1} bud\u{00FA} na oblohe oddelen\u{00E9} uhlom asi {$deg}\u{00B0}, \u{010D}o umo\u{017E}\u{0148}uje ich spolo\u{010D}n\u{00E9} pozorovanie.", 'short' => "{$body} pri Mesiaci ({$deg}\u{00B0}) pribli\u{017E}ne {$date}."],
                    ['description' => "{$body} sa pribli\u{017E}ne {$date} pribl\u{00ED}\u{017E}i k Mesiacu na {$deg}\u{00B0}. Pri vhodn\u{00FD}ch podmienkach je jav dobre pozorovate\u{013E}n\u{00FD} vo\u{013E}n\u{00FD}m okom aj men\u{0161}\u{00ED}m \u{010F}alekoh\u{013E}adom.", 'short' => "{$body} v konjunkcii s Mesiacom ({$deg}\u{00B0}) pribli\u{017E}ne {$date}."],
                ];
            } else {
                $variants = [
                    ['description' => "{$body} sa priblíži k {$target} na {$deg}° a bude viditeľný vedľa neho na oblohe. Zblíženie je pozorovateľné voľným okom alebo ďalekohľadom za jasnej noci.", 'short' => "Konjunkcia {$body} a {$target} ({$deg}°) pribli\u{017E}ne {$date}."],
                    ['description' => "{$body} a {$target} sa znájdu zdanlivo blízko seba na oblohe — uhlová vzdialenosť bude {$deg}°. Pozorovanie je možné voľným okom za jasnej noci pribli\u{017E}ne {$date}.", 'short' => "{$body} vedľa {$target} ({$deg}°) pribli\u{017E}ne {$date}."],
                    ['description' => "Zblíženie {$body} a {$target} nastane pribli\u{017E}ne {$date}. Oba objekty budú v tesnej blízkosti na nočnej oblohe s uhlovou vzdialenosťou {$deg}°.", 'short' => "{$body} pri {$target} ({$deg}°) pribli\u{017E}ne {$date}."],
                ];
            }
            return $variants[$v];
        }

        if ($type === 'planetary_event' || Str::contains($type, ['conjunct', 'planet', 'opposition'])) {
            $variants = [
                [
                    'description' => "{$title} nastane pribli\u{017E}ne {$date}. Ide o zaujímavý planetárny úkaz pozorovateľný na nočnej oblohe. Ďalekohľad môže výrazne vylepšiť zážitok z pozorovania.",
                    'short' => "{$title} pribli\u{017E}ne {$date}.",
                ],
                [
                    'description' => "Planetárny úkaz {$title} nastane okolo {$date}. Pozorovanie je možné voľným okom alebo ďalekohľadom za jasnej noci. Vhodné podmienky výrazne zvyšujú kvalitu pozorovania.",
                    'short' => "{$title} okolo {$date}.",
                ],
                [
                    'description' => "Okolo {$date} nastane úkaz {$title}. Tento jav je dobre viditeľný pri jasnej oblohe. Na detailné pozorovanie odporúčame ďalekohľad alebo astronomický ďalekohľad.",
                    'short' => "{$title} pribli\u{017E}ne {$date}.",
                ],
            ];
            return $variants[$v];
        }

        if ($type === 'observation_window') {
            $variants = [
                [
                    'description' => "Pozorovacie okno {$title} nastane pribli\u{017E}ne {$date}. Ide o vhodné časové okno na astronomické pozorovanie pri priaznivých podmienkach. Odporúčame tmavé miesto bez svetelného znečistenia.",
                    'short' => "{$title} pribli\u{017E}ne {$date}.",
                ],
                [
                    'description' => "Okolo {$date} je priaznivé pozorovacie okno: {$title}. Využite ho na pozorovanie nočnej oblohy. Kvalitu pozorovania ovplyvňuje oblačnosť a svetelné znečistenie.",
                    'short' => "Pozorovacie okno: {$title} — {$date}.",
                ],
                [
                    'description' => "{$title} — pozorovacie okno pribli\u{017E}ne {$date}. Za jasnej noci a z tmavého miesta sú podmienky na pozorovanie oblohy najlepšie. Ďalekohľad nie je podmienkou.",
                    'short' => "{$title} pribli\u{017E}ne {$date}.",
                ],
            ];
            return $variants[$v];
        }

        $variants = [
            [
                'description' => "Udalosť {$title} nastane pribli\u{017E}ne {$date}. Pozorovanie je možné pri vhodných podmienkach. Sledujte aktuálne astronomické predpovede.",
                'short' => "{$title} pribli\u{017E}ne {$date}.",
            ],
            [
                'description' => "Astronomická udalosť {$title} je naplánovaná na pribli\u{017E}ne {$date}. Odporúčame pozorovanie z tmavého miesta ďaleko od svetelného znečistenia.",
                'short' => "{$title} okolo {$date}.",
            ],
            [
                'description' => "Okolo {$date} nastane astronomická udalosť {$title}. Podmienky pozorovania závisia od počasia a miestneho svetelného znečistenia.",
                'short' => "{$title} pribli\u{017E}ne {$date}.",
            ],
        ];
        return $variants[$v];
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
            return "v neur\u{010D}enom \u{010D}ase";
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

    /**
     * Extract a formatted distance in km from a raw title like "Moon at Apogee: 406421 km".
     * Returns e.g. "406 421" (with space thousands separator, Slovak convention).
     */
    private function extractFormattedKm(string $rawTitle): ?string
    {
        if (preg_match('/(\d[\d\s,]+)\s*km/i', $rawTitle, $m) !== 1) {
            return null;
        }
        $digits = preg_replace('/\D/', '', $m[1]);
        if ($digits === null || $digits === '' || strlen($digits) < 4) {
            return null;
        }
        return number_format((int) $digits, 0, ',', ' ');
    }

    /**
     * Extract conjunction data from a raw English title like "Jupiter 0.8°S of Moon".
     *
     * @return array{body:string,deg:string,isMoon:bool,target:string}|null
     */
    private function extractConjunctionData(string $rawTitle, string $translatedTitle): ?array
    {
        // Pattern: "Body X.X°N/S of Target"  or  "Body X.X° N of Target"
        if (preg_match(
            '/^([a-z\s\-\']+?)\s+([\d]+(?:[.,]\d+)?)\s*[°º]?\s*([ns])\s+of\s+(?:the\s+)?(.+)$/i',
            trim($rawTitle),
            $m
        ) !== 1) {
            return null;
        }

        $body   = ucfirst(trim($m[1]));
        $deg    = str_replace('.', ',', $m[2]);
        $target = ucfirst(trim($m[4]));
        $isMoon = preg_match('/\bmoon\b/i', $target) === 1;

        // Use localized planet name from translated title when possible
        if (preg_match('/^([^\d]+?)\s+[\d]/u', $translatedTitle, $tm) === 1) {
            $localBody = trim($tm[1]);
            if ($localBody !== '') {
                $body = $localBody;
            }
        }

        // Localize target when it's the Moon
        if ($isMoon) {
            $target = 'Mesiaca';
        }

        return ['body' => $body, 'deg' => $deg, 'isMoon' => $isMoon, 'target' => $target];
    }

    private function isDescriptionRefinementEnabled(): bool
    {
        return (bool) config(
            'events.refine_descriptions_with_ollama',
            config('ai.ollama_refinement_enabled', false)
        );
    }

    private function shouldRunRefinement(bool $usedTemplateFallback, string $requestedMode): bool
    {
        if ($requestedMode === self::REQUESTED_MODE_TEMPLATE) {
            return false;
        }

        // Explicit admin AI requests always run refinement regardless of global flag.
        if ($this->isExplicitAiModeRequest()) {
            return true;
        }

        if (! $this->isDescriptionRefinementEnabled()) {
            return false;
        }

        if ($usedTemplateFallback && (bool) config('events.translation.refinement.skip_on_template_fallback', true)) {
            return false;
        }

        return true;
    }

    private function isExplicitAiModeRequest(): bool
    {
        return strtolower(trim((string) $this->requestedMode)) === self::REQUESTED_MODE_AI;
    }

    private function resolveRequestedMode(): string
    {
        $normalized = strtolower(trim((string) $this->requestedMode));

        return in_array($normalized, [self::REQUESTED_MODE_AI, self::REQUESTED_MODE_TEMPLATE], true)
            ? $normalized
            : self::REQUESTED_MODE_AI;
    }

    private function syncPublishedEventFromCandidate(
        EventCandidate $candidate,
        EventDescriptionOriginRecorder $originRecorder,
        string $requestedMode,
        string $translationMode
    ): void {
        $eventId = (int) ($candidate->published_event_id ?? 0);
        if ($eventId <= 0) {
            return;
        }

        if ((string) ($candidate->status ?? '') !== EventCandidate::STATUS_APPROVED) {
            return;
        }

        $event = Event::query()->find($eventId);
        if (! $event) {
            return;
        }

        $resolvedTitle = $this->sanitizeInline((string) ($candidate->translated_title ?? ''))
            ?: $this->sanitizeInline((string) ($candidate->title ?? ''));
        if ($resolvedTitle === '') {
            return;
        }

        $resolvedDescription = $this->sanitizeDescription((string) ($candidate->translated_description ?? ''))
            ?? $this->sanitizeDescription((string) ($candidate->description ?? ''));
        $resolvedShort = $this->sanitizeShort((string) ($candidate->short ?? ''))
            ?? $this->buildShort($resolvedDescription, $resolvedTitle);

        $event->update([
            'title' => $resolvedTitle,
            'description' => $resolvedDescription,
            'short' => $resolvedShort,
        ]);

        $originRecorder->record(
            event: $event->fresh() ?? $event,
            source: $this->resolvePublishedEventSyncOriginSource($translationMode),
            sourceDetail: 'candidate_retranslation_sync',
            candidateId: (int) $candidate->id,
            meta: [
                'requested_mode' => $requestedMode,
                'translation_mode' => $translationMode,
                'translation_status' => (string) ($candidate->translation_status ?? ''),
                'translation_error' => $candidate->translation_error,
            ]
        );
    }

    private function resolvePublishedEventSyncOriginSource(string $translationMode): string
    {
        return match ($translationMode) {
            EventCandidate::TRANSLATION_MODE_AI_REFINED => 'candidate_retranslate_ai',
            EventCandidate::TRANSLATION_MODE_TEMPLATE => 'candidate_retranslate_template',
            default => 'candidate_retranslate',
        };
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
            $title = self::GENERIC_EVENT_TITLE;
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
            $title = self::GENERIC_EVENT_TITLE;
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
