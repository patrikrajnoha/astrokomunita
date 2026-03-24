<?php

namespace App\Services\Translation;

class AstronomyPhraseNormalizer
{
    /**
     * @var array<string,string>
     */
    private const SK_PATTERNS = [
        // Czech/Latin form "studium" → correct Slovak "štúdium"
        '/\bStudium\b/u' => "\u{0160}t\u{00FA}dium",
        '/\bstudium\b/u' => "\u{0161}t\u{00FA}dium",
        // "White Paper(s)" often mistranslated literally — keep in English
        '/\bwhite\s+papers?\b/iu' => 'White Paper',
        '/\bprv(?:[iIyY]|\x{00FD}|\x{0130}|\x{0131})\s+(?:\x{0161}tvr(?:\x{0165}|t)|stvrt)\s+mesiac(?:a|om)?\b/iu' => "Prv\u{00E1} \u{0161}tvr\u{0165} Mesiaca",
        '/\bposledn(?:[iIyY]|\x{00FD}|\x{0130}|\x{0131})\s+(?:\x{0161}tvr(?:\x{0165}|t)|stvrt)\s+mesiac(?:a|om)?\b/iu' => "Posledn\u{00E1} \u{0161}tvr\u{0165} Mesiaca",
        '/\bprv(?:a|\x{00E1})\s+tla(?:c|\x{010D})\s+mesiaca\b/iu' => "Prv\u{00E1} \u{0161}tvr\u{0165} Mesiaca",
        '/\bpolo(?:z|\x{017E})en(?:a|\x{00E1})\s+tla(?:c|\x{010D})\s+mesiaca\b/iu' => "Posledn\u{00E1} \u{0161}tvr\u{0165} Mesiaca",
        '/\bprv(?:[iIyY]|\x{00FD}|\x{0130}|\x{0131})\s+(?:quarter|kvartn\pL*|\x{0161}t[a\x{00E1}]t|stat)\s+moon\b/iu' => "Prv\u{00E1} \u{0161}tvr\u{0165} Mesiaca",
        '/\bposledn(?:[iIyY]|\x{00FD}|\x{0130}|\x{0131})\s+(?:quarter|kvartn\pL*|\x{0161}t[a\x{00E1}]t|stat)\s+moon\b/iu' => "Posledn\u{00E1} \u{0161}tvr\u{0165} Mesiaca",
        '/\bposledn(?:[iIyY]|\x{00FD}|\x{0130}|\x{0131})\s+(?:\x{0161}t[a\x{00E1}]t|stat)\b/iu' => "Posledn\u{00E1} \u{0161}tvr\u{0165} Mesiaca",
        '/\bfirst\s+quarter\s+moon\b/iu' => "Prv\u{00E1} \u{0161}tvr\u{0165} Mesiaca",
        '/\blast\s+quarter\s+moon\b/iu' => "Posledn\u{00E1} \u{0161}tvr\u{0165} Mesiaca",
        '/\bnov(?:[iIyY]|\x{00FD}|\x{0130}|\x{0131})\s+moon\b/iu' => 'Nov',
        '/\bnew\s+moon\b/iu' => 'Nov',
        '/\bfull\s+moon\b/iu' => 'Spln',
        '/\blunar\s+eclipse\b/iu' => "Mesa\u{010D}n\u{00E9} zatmenie",
        '/\bsolar\s+eclipse\b/iu' => "Slne\u{010D}n\u{00E9} zatmenie",
        '/\blunar\s+phase\b/iu' => "f\u{00E1}za Mesiaca",
        '/\bgeminid(?:s)?\b/iu' => 'Geminidy',
        '/\bperseid(?:s)?\b/iu' => 'Perzeidy',
        '/\bleonid(?:s)?\b/iu' => 'Leonidy',
        '/\bpleiades\b/iu' => "Plej\u{00E1}dy",
        '/\bplejady\b/iu' => "Plej\u{00E1}dy",
        '/\blyrid(?:s)?\b/iu' => 'Lyridy',
        '/\borionid(?:s)?\b/iu' => 'Orionidy',
        '/\bquadrantid(?:s)?\b/iu' => 'Kvadrantidy',
        '/\bursid(?:s)?\b/iu' => 'Ursidy',
        '/\bdelta-aquarid(?:s)?\b/iu' => 'Delta-Akvaridy',
        '/\beta-aquarid(?:s)?\b/iu' => 'Eta-Akvaridy',
        '/\bs\s+taurid(?:s)?\b/iu' => 'Juzne Tauridy',
        '/\bn\s+taurid(?:s)?\b/iu' => 'Severne Tauridy',
        '/\btaurid(?:s)?\b/iu' => 'Tauridy',
        '/\b([\pL][\pL\-]*(?:\s+[\pL][\pL\-]*){0,3})(?:\s+\([A-Z]{2,5}\))?\s+meteor\s+sprcha\b/iu' => 'Meteoricky roj $1',
        '/\b([\pL][\pL\-]*(?:\s+[\pL][\pL\-]*){0,3})(?:\s+\([A-Z]{2,5}\))?\s+meteorick[^\s]*\s+sprcha\b/iu' => 'Meteoricky roj $1',
        '/\b([\pL][\pL\-]*(?:\s+[\pL][\pL\-]*){0,3})(?:\s+\([A-Z]{2,5}\))?\s+meteor(?:ic)?\s+shower\b/iu' => 'Meteoricky roj $1',
        '/\b([\pL][\pL\-]*(?:\s+[\pL][\pL\-]*){0,3})(?:\s+\([A-Z]{2,5}\))?\s+meteorick(?:\x{00FD}|y)\s+roj\b(?=$|[.,;:!?])/iu' => 'Meteoricky roj $1',
        '/\b(Meteorick(?:\x{00FD}|y)\s+roj)\s+Juzne\s+Tauridy\b(?=$|[.,;:!?])/iu' => '$1 Juznych Taurid',
        '/\b(Meteorick(?:\x{00FD}|y)\s+roj)\s+Severne\s+Tauridy\b(?=$|[.,;:!?])/iu' => '$1 Severnych Taurid',
        '/\b(Meteorick(?:\x{00FD}|y)\s+roj)\s+([\pL][\pL\-]*(?:\s+[\pL][\pL\-]*)?)idy\b(?=$|[.,;:!?])/iu' => '$1 $2id',
        '/\bmeteorick[^\s]*\s+sprcha\b/iu' => 'meteoricky roj',
        '/\bmeteor\s+sprcha\b/iu' => 'meteoricky roj',
        '/\bmeteor\s+shower\b/iu' => "meteorick\u{00FD} roj",
        '/\bvisibility\s+from\s+slovakia\b/iu' => "vidite\u{013E}nos\u{0165} zo Slovenska",
        '/\bdepends\s+on\s+local\s+weather\b/iu' => "z\u{00E1}vis\u{00ED} od miestneho po\u{010D}asia",
        '/\bin\s+conjunction\s+with\s+sun\b/iu' => 'v konjunkcii so Slnkom',
        '/\bin\s+conjunction\s+with\s+slnko\b/iu' => 'v konjunkcii so Slnkom',
        '/\bin\s+conjunction\s+with\b/iu' => 'v konjunkcii s',
        '/\bv\s+konflikte\s+so\s+slnkom\b/iu' => 'v konjunkcii so Slnkom',
        '/\bv\s+superior\s+conjunction\b/iu' => 'v hornej konjunkcii',
        '/\bv\s+inferior\s+conjunction\b/iu' => 'v dolnej konjunkcii',
        '/\bat\s+superior\s+conjunction\b/iu' => 'v hornej konjunkcii',
        '/\bat\s+inferior\s+conjunction\b/iu' => 'v dolnej konjunkcii',
        '/\bat\s+perihelion\b/iu' => "v perih\u{00E9}liu",
        '/\bat\s+aphelion\b/iu' => "v af\u{00E9}liu",
        '/\bat\s+opposition\b/iu' => "v opoz\u{00ED}cii",
        '/\bEarth\s+v\s+perih(?:\x{00E9}|e)liu\b/iu' => "Zem v perih\u{00E9}liu",
        '/\bEarth\s+v\s+af(?:\x{00E9}|e)liu\b/iu' => "Zem v af\u{00E9}liu",
        '/\bEarth\s+v\s+opoz(?:\x{00ED}|i)cii\b/iu' => "Zem v opoz\u{00ED}cii",
        '/\bsuperior\s+conjunction\b/iu' => 'horna konjunkcia',
        '/\binferior\s+conjunction\b/iu' => 'dolna konjunkcia',
        '/\b((?:Mercury|Merk(?:u|\x{00FA})r|Ortu\S*|Venus|Venu(?:s|\x{0161})a?|Venu\S*|Mars|Jupiter|Saturn|Uran\S*|Nept\S*))\s+na\s+vrchole\b/iu' => '$1 v hornej konjunkcii',
        '/\b((?:Mercury|Merk(?:u|\x{00FA})r|Ortu\S*|Venus|Venu(?:s|\x{0161})a?|Venu\S*))\s+pri\s+odraze\s*ferora\b/iu' => '$1 v dolnej konjunkcii',
        '/\bOrtu(?:\x{0165}|t)\b/iu' => "Merk\u{00FA}r",
        '/\bmoon\b/iu' => 'Mesiac',
        '/\bsun\b/iu' => 'Slnko',
    ];

    /**
     * @var array<int,string>
     */
    private const SK_RESIDUAL_ENGLISH_PATTERNS = [
        '/\bin\s+conjunction\b/iu',
        '/\bat\s+conjunction\b/iu',
        '/\b(?:superior|inferior)\s+conjunction\b/iu',
        '/\bconjunction\b/iu',
        '/\bat\s+perihelion\b/iu',
        '/\bat\s+aphelion\b/iu',
        '/\bat\s+opposition\b/iu',
        '/\bperihelion\b/iu',
        '/\baphelion\b/iu',
        '/\bopposition\b/iu',
        '/\bquarter\s+moon\b/iu',
        '/\bmeteor\s+sprcha\b/iu',
        '/\bmeteorick(?:\x{00E1}|a)\s+sprcha\b/iu',
        '/\bmeteor\s+shower\b/iu',
        '/\bmeteoric\s+shower\b/iu',
        '/\blunar\s+phase\b/iu',
        '/\bvisibility\s+from\s+slovakia\b/iu',
        '/\bdepends\s+on\s+local\s+weather\b/iu',
        '/\bwith\s+(?:sun|moon)\b/iu',
        '/\bwith\s+slnko\b/iu',
        '/\bwith\s+mesiac(?:om|a)?\b/iu',
        '/\b(?:in|at)\s+konjunkci(?:a|i|u|ou)\b/iu',
        '/\bsun\b/iu',
        '/\bmoon\b/iu',
    ];

    /**
     * @var array<string,string>
     */
    private const PLANET_LOCALIZED_MAP = [
        'earth' => 'Zem',
        'mercury' => "Merk\u{00FA}r",
        'venus' => "Venu\u{0161}a",
        'mars' => 'Mars',
        'jupiter' => 'Jupiter',
        'saturn' => 'Saturn',
        'uranus' => "Ur\u{00E1}n",
        'neptune' => "Nept\u{00FA}n",
        'pluto' => 'Pluto',
    ];

    /**
     * @var array<string,string>
     */
    private const OBJECT_GENITIVE_MAP = [
        'earth' => 'Zeme',
        'mercury' => "Merk\u{00FA}ra",
        'venus' => "Venu\u{0161}e",
        'mars' => 'Marsu',
        'jupiter' => 'Jupitera',
        'saturn' => 'Saturna',
        'uranus' => "Ur\u{00E1}na",
        'neptune' => "Nept\u{00FA}na",
        'pluto' => 'Pluta',
        'moon' => 'Mesiaca',
        'sun' => 'Slnka',
        'pleiades' => "Plej\u{00E1}d",
    ];

    /**
     * @var array<string,string>
     */
    private const OBJECT_NOMINATIVE_MAP = [
        'moon' => 'Mesiac',
        'sun' => 'Slnko',
        'pleiades' => "Plej\u{00E1}dy",
    ];

    /**
     * @var array<string,string>
     */
    private const DIRECTION_LOCALIZED_MAP = [
        'n' => 'severne',
        's' => "ju\u{017E}ne",
        'e' => "v\u{00FD}chodne",
        'w' => "z\u{00E1}padne",
    ];

    public function normalize(string $text, string $language): string
    {
        if (! $this->isSlovakLanguage($language)) {
            return $text;
        }

        $value = $text;
        foreach (self::SK_PATTERNS as $pattern => $replacement) {
            $value = preg_replace($pattern, $replacement, $value) ?? $value;
        }

        $value = $this->normalizeDirectionalTitle($value);

        return $value;
    }

    public function hasResidualEnglishTokens(string $text, string $language): bool
    {
        if (! $this->isSlovakLanguage($language)) {
            return false;
        }

        foreach (self::SK_RESIDUAL_ENGLISH_PATTERNS as $pattern) {
            if (preg_match($pattern, $text) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{title:string,used_fallback:bool,reason:?string}
     */
    public function normalizeTitleWithFallback(string $translatedTitle, string $originalTitle, string $language = 'sk'): array
    {
        $candidate = $this->normalize($this->normalizeInline($translatedTitle), $language);
        $hasResidualEnglish = $this->hasResidualEnglishTokens($candidate, $language);
        $hasSuspiciousArtifacts = $this->hasSuspiciousEncodingArtifacts($candidate);
        $expectedDeterministic = $this->expectedDeterministicMetaFromOriginal($originalTitle, $language);

        if ($candidate === '') {
            $candidate = $this->normalize($this->normalizeInline($originalTitle), $language);
            $hasResidualEnglish = $this->hasResidualEnglishTokens($candidate, $language);
            $hasSuspiciousArtifacts = $this->hasSuspiciousEncodingArtifacts($candidate);
        }

        if ($candidate !== '' && ! $hasResidualEnglish && ! $hasSuspiciousArtifacts) {
            if ($expectedDeterministic !== null && $this->isDeterministicPlanetMismatch($candidate, $expectedDeterministic)) {
                return [
                    'title' => $expectedDeterministic['title'],
                    'used_fallback' => true,
                    'reason' => 'deterministic_original_mismatch',
                ];
            }

            return [
                'title' => $candidate,
                'used_fallback' => false,
                'reason' => null,
            ];
        }

        if ($expectedDeterministic !== null) {
            return [
                'title' => $expectedDeterministic['title'],
                'used_fallback' => true,
                'reason' => 'deterministic_original',
            ];
        }

        $fallbackFromOriginal = $this->normalize($this->normalizeInline($originalTitle), $language);
        if ($fallbackFromOriginal !== '' && ! $this->hasResidualEnglishTokens($fallbackFromOriginal, $language)) {
            return [
                'title' => $fallbackFromOriginal,
                'used_fallback' => true,
                'reason' => 'normalized_original',
            ];
        }

        return [
            'title' => 'Astronomická udalosť',
            'used_fallback' => true,
            'reason' => 'generic_fallback',
        ];
    }

    private function isSlovakLanguage(string $language): bool
    {
        $lang = strtolower(trim($language));
        if ($lang === '') {
            return false;
        }

        return $lang === 'sk' || str_starts_with($lang, 'sk-');
    }

    private function normalizeInline(string $value): string
    {
        $normalized = trim(strip_tags($value));
        if ($normalized === '') {
            return '';
        }

        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;

        return trim($normalized);
    }

    private function normalizeDirectionalTitle(string $text): string
    {
        $normalized = $this->normalizeInline($text);
        if ($normalized === '') {
            return $text;
        }

        $patterns = [
            '/^(?<subject>.+?)\s+(?<distance>\d+(?:[.,]\d+)?)\s*(?:\x{00B0})?\s*(?<dir>[NSEW])\s+of\s+(?<target>.+)$/iu',
            '/^(?<subject>.+?)\s+(?<distance>\d+(?:[.,]\d+)?)\s*(?:\x{00B0})?\s*(?<dir>(?:[NSEW]|north|south|east|west|severne|juzne|vychodne|zapadne|s\.?\s*(?:\x{0161}|s)\.?|j\.?\s*(?:\x{0161}|s)\.?|v\.?\s*d\.?|z\.?\s*d\.?))\s+(?:od\s+)?(?<target>.+)$/iu',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $normalized, $matches) !== 1) {
                continue;
            }

            $formatted = $this->formatDirectionalTitle(
                (string) ($matches['subject'] ?? ''),
                (string) ($matches['distance'] ?? ''),
                (string) ($matches['dir'] ?? ''),
                (string) ($matches['target'] ?? '')
            );

            if ($formatted !== null) {
                return $formatted;
            }
        }

        return $text;
    }

    private function formatDirectionalTitle(string $subject, string $distance, string $directionToken, string $target): ?string
    {
        $directionKey = $this->directionKey($directionToken);
        if ($directionKey === null) {
            return null;
        }

        $localizedDirection = self::DIRECTION_LOCALIZED_MAP[$directionKey] ?? null;
        if ($localizedDirection === null) {
            return null;
        }

        $normalizedSubject = $this->localizeObjectNominative($subject);
        $normalizedTarget = $this->localizeObjectGenitive($target);
        $normalizedDistance = $this->normalizeAngleToken($distance);

        if ($normalizedSubject === '' || $normalizedTarget === '' || $normalizedDistance === '') {
            return null;
        }

        return sprintf(
            "%s %s\u{00B0} %s od %s",
            $normalizedSubject,
            $normalizedDistance,
            $localizedDirection,
            $normalizedTarget
        );
    }

    private function hasSuspiciousEncodingArtifacts(string $text): bool
    {
        if ($text === '') {
            return false;
        }

        return preg_match('/\pL\?(?:\s|$)/u', $text) === 1
            || str_contains($text, "\u{FFFD}");
    }

    private function localizePlanet(string $planet): ?string
    {
        $key = strtolower(trim($planet));
        if ($key === '') {
            return null;
        }

        return self::PLANET_LOCALIZED_MAP[$key] ?? null;
    }

    private function localizeObjectNominative(string $value): string
    {
        $key = $this->objectKey($value);
        if ($key !== null) {
            return self::PLANET_LOCALIZED_MAP[$key]
                ?? self::OBJECT_NOMINATIVE_MAP[$key]
                ?? $this->normalizeInline($value);
        }

        return $this->normalizeInline($value);
    }

    private function localizeObjectGenitive(string $value): string
    {
        $key = $this->objectKey($value);
        if ($key !== null) {
            return self::OBJECT_GENITIVE_MAP[$key] ?? $this->normalizeInline($value);
        }

        return $this->normalizeInline($value);
    }

    /**
     * @return array{title:string,planet:string,type:string}|null
     */
    private function expectedDeterministicMetaFromOriginal(string $originalTitle, string $language): ?array
    {
        if (! $this->isSlovakLanguage($language)) {
            return null;
        }

        $normalized = $this->normalizeInline($originalTitle);
        if ($normalized === '') {
            return null;
        }

        if (preg_match('/^(?<planet>Mercury|Venus|Mars|Jupiter|Saturn|Uranus|Neptune|Pluto)\s+in\s+Conjunction\s+with\s+Sun$/iu', $normalized, $matches) === 1) {
            $planet = $this->localizePlanet((string) ($matches['planet'] ?? ''));
            $planetKey = $this->planetKey((string) ($matches['planet'] ?? ''));
            if ($planet !== null && $planetKey !== null) {
                return [
                    'title' => "{$planet} v konjunkcii so Slnkom",
                    'planet' => $planetKey,
                    'type' => 'with_sun',
                ];
            }
        }

        if (preg_match('/^(?<planet>Mercury|Venus)\s+at\s+Superior\s+Conjunction$/iu', $normalized, $matches) === 1) {
            $planet = $this->localizePlanet((string) ($matches['planet'] ?? ''));
            $planetKey = $this->planetKey((string) ($matches['planet'] ?? ''));
            if ($planet !== null && $planetKey !== null) {
                return [
                    'title' => "{$planet} v hornej konjunkcii",
                    'planet' => $planetKey,
                    'type' => 'superior',
                ];
            }
        }

        if (preg_match('/^(?<planet>Mercury|Venus)\s+at\s+Inferior\s+Conjunction$/iu', $normalized, $matches) === 1) {
            $planet = $this->localizePlanet((string) ($matches['planet'] ?? ''));
            $planetKey = $this->planetKey((string) ($matches['planet'] ?? ''));
            if ($planet !== null && $planetKey !== null) {
                return [
                    'title' => "{$planet} v dolnej konjunkcii",
                    'planet' => $planetKey,
                    'type' => 'inferior',
                ];
            }
        }

        if (preg_match('/^(?<planet>Earth|Mercury|Venus|Mars|Jupiter|Saturn|Uranus|Neptune|Pluto)\s+at\s+(?<event>Perihelion|Aphelion)(?<suffix>\s*:\s*(?<distance>[0-9]+(?:[.,][0-9]+)?)\s*AU)?$/iu', $normalized, $matches) === 1) {
            $planet = $this->localizePlanet((string) ($matches['planet'] ?? ''));
            $planetKey = $this->planetKey((string) ($matches['planet'] ?? ''));
            $eventType = strtolower((string) ($matches['event'] ?? ''));
            $distance = trim((string) ($matches['distance'] ?? ''));
            $localizedEvent = match ($eventType) {
                'perihelion' => "v perih\u{00E9}liu",
                'aphelion' => "v af\u{00E9}liu",
                default => null,
            };
            if ($planet !== null && $planetKey !== null && $localizedEvent !== null) {
                $title = "{$planet} {$localizedEvent}";
                if ($distance !== '') {
                    $title .= ": {$distance} AU";
                }

                return [
                    'title' => $title,
                    'planet' => $planetKey,
                    'type' => $eventType,
                ];
            }
        }

        if (preg_match('/^(?<planet>Mercury|Venus|Mars|Jupiter|Saturn|Uranus|Neptune|Pluto)\s+at\s+Opposition$/iu', $normalized, $matches) === 1) {
            $planet = $this->localizePlanet((string) ($matches['planet'] ?? ''));
            $planetKey = $this->planetKey((string) ($matches['planet'] ?? ''));
            if ($planet !== null && $planetKey !== null) {
                return [
                    'title' => "{$planet} v opoz\u{00ED}cii",
                    'planet' => $planetKey,
                    'type' => 'opposition',
                ];
            }
        }

        return null;
    }

    /**
     * @param  array{title:string,planet:string,type:string}  $expected
     */
    private function isDeterministicPlanetMismatch(string $candidateTitle, array $expected): bool
    {
        $actual = $this->actualDeterministicMetaFromTranslated($candidateTitle);
        if ($actual === null) {
            return false;
        }

        return $actual['planet'] !== $expected['planet'] || $actual['type'] !== $expected['type'];
    }

    /**
     * @return array{planet:string,type:string}|null
     */
    private function actualDeterministicMetaFromTranslated(string $translatedTitle): ?array
    {
        $normalized = $this->normalizeInline($translatedTitle);
        if ($normalized === '') {
            return null;
        }

        if (preg_match('/^(?<planet>.+?)\s+v\s+konjunkcii\s+so\s+slnkom$/iu', $normalized, $matches) === 1) {
            $planet = $this->planetKey((string) ($matches['planet'] ?? ''));
            if ($planet !== null) {
                return [
                    'planet' => $planet,
                    'type' => 'with_sun',
                ];
            }
        }

        if (preg_match('/^(?<planet>.+?)\s+v\s+hornej\s+konjunkcii$/iu', $normalized, $matches) === 1) {
            $planet = $this->planetKey((string) ($matches['planet'] ?? ''));
            if ($planet !== null) {
                return [
                    'planet' => $planet,
                    'type' => 'superior',
                ];
            }
        }

        if (preg_match('/^(?<planet>.+?)\s+v\s+dolnej\s+konjunkcii$/iu', $normalized, $matches) === 1) {
            $planet = $this->planetKey((string) ($matches['planet'] ?? ''));
            if ($planet !== null) {
                return [
                    'planet' => $planet,
                    'type' => 'inferior',
                ];
            }
        }

        if (preg_match('/^(?<planet>.+?)\s+v\s+perih(?:\x{00E9}|e)liu(?:\s*:\s*[0-9]+(?:[.,][0-9]+)?\s*AU)?$/iu', $normalized, $matches) === 1) {
            $planet = $this->planetKey((string) ($matches['planet'] ?? ''));
            if ($planet !== null) {
                return [
                    'planet' => $planet,
                    'type' => 'perihelion',
                ];
            }
        }

        if (preg_match('/^(?<planet>.+?)\s+v\s+af(?:\x{00E9}|e)liu(?:\s*:\s*[0-9]+(?:[.,][0-9]+)?\s*AU)?$/iu', $normalized, $matches) === 1) {
            $planet = $this->planetKey((string) ($matches['planet'] ?? ''));
            if ($planet !== null) {
                return [
                    'planet' => $planet,
                    'type' => 'aphelion',
                ];
            }
        }

        if (preg_match('/^(?<planet>.+?)\s+v\s+opoz(?:\x{00ED}|i)cii$/iu', $normalized, $matches) === 1) {
            $planet = $this->planetKey((string) ($matches['planet'] ?? ''));
            if ($planet !== null) {
                return [
                    'planet' => $planet,
                    'type' => 'opposition',
                ];
            }
        }

        return null;
    }

    private function planetKey(string $value): ?string
    {
        $normalized = mb_strtolower(trim($value), 'UTF-8');
        if ($normalized === '') {
            return null;
        }

        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;
        $normalized = trim($normalized);
        $normalized = $this->asciiPlanetToken($normalized);
        if ($normalized === '') {
            return null;
        }

        return match ($normalized) {
            'earth', 'zem', 'zeme' => 'earth',
            'mercury', 'merkur', 'merkura', 'ortut', 'ortu' => 'mercury',
            'venus', 'venusa', 'venuse' => 'venus',
            'mars', 'marsu' => 'mars',
            'jupiter', 'jupitera' => 'jupiter',
            'saturn', 'saturna' => 'saturn',
            'uranus', 'uran', 'urana' => 'uranus',
            'neptune', 'neptun', 'neptuna' => 'neptune',
            'pluto', 'pluta' => 'pluto',
            default => null,
        };
    }

    private function objectKey(string $value): ?string
    {
        $planet = $this->planetKey($value);
        if ($planet !== null) {
            return $planet;
        }

        $normalized = mb_strtolower(trim($value), 'UTF-8');
        if ($normalized === '') {
            return null;
        }

        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;
        $normalized = trim($normalized);
        $normalized = $this->asciiPlanetToken($normalized);
        if ($normalized === '') {
            return null;
        }

        return match ($normalized) {
            'moon', 'mesiac', 'mesiaca' => 'moon',
            'sun', 'slnko', 'slnka' => 'sun',
            'pleiades', 'plejady', 'plejad' => 'pleiades',
            default => null,
        };
    }

    private function directionKey(string $value): ?string
    {
        $normalized = mb_strtolower(trim($value), 'UTF-8');
        if ($normalized === '') {
            return null;
        }

        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized);
        $normalized = $ascii !== false ? strtolower($ascii) : strtolower($normalized);
        $normalized = preg_replace('/[^a-z]+/', '', $normalized) ?? $normalized;

        return match ($normalized) {
            'n', 'north', 'severne', 'ss' => 'n',
            's', 'south', 'juzne', 'js' => 's',
            'e', 'east', 'vychodne', 'vd' => 'e',
            'w', 'west', 'zapadne', 'zd' => 'w',
            default => null,
        };
    }

    private function normalizeAngleToken(string $value): string
    {
        $normalized = trim($value);
        if ($normalized === '') {
            return '';
        }

        $normalized = preg_replace('/\s+/u', '', $normalized) ?? $normalized;
        return str_replace('.', ',', $normalized);
    }

    private function asciiPlanetToken(string $value): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        $normalized = $ascii !== false ? strtolower($ascii) : strtolower($value);

        return preg_replace('/[^a-z]+/', '', $normalized) ?? '';
    }
}
