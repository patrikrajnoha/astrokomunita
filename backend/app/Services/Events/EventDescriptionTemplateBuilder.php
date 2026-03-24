<?php

namespace App\Services\Events;

use App\Models\Event;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

class EventDescriptionTemplateBuilder
{
    /**
     * @return array{description:string,short:string,provider:string}
     */
    public function build(Event $event): array
    {
        $title = $this->normalizeTitle((string) ($event->title ?? 'Astronomická udalosť'));
        $normalized = $this->normalizeForMatching($title);
        $when = $this->formatWhen($this->resolveMoment($event));
        $region = $this->regionPhrase((string) ($event->region_scope ?? 'global'));

        if ($this->containsAny($normalized, ['spln', 'full moon'])) {
            return $this->finalizeFromVariants($event, 'moon.full', [
                "Mesiac dosahuje fázu splnu {$when}. Osvetlená je celá privrátená pologuľa Mesiaca, čo výrazne zvyšuje jas nočnej oblohy.",
                "Spln Mesiaca nastáva {$when}. Zvýšený mesačný jas môže znižovať kontrast slabších objektov hlbokého vesmíru.",
                "V čase {$when} je Mesiac v splne. Pozorovanie detailov mesačného disku je priaznivé, no slabé deep-sky objekty sú menej kontrastné.",
            ], [
                "Spln Mesiaca {$when}.",
                "Mesiac je v splne {$when}.",
                "Fáza splnu Mesiaca {$when}.",
            ]);
        }

        if ($this->containsAny($normalized, ['nov', 'new moon'])) {
            return $this->finalizeFromVariants($event, 'moon.new', [
                "Mesiac dosahuje fázu novu {$when}. Keď je v konjunkcii so Slnkom, na nočnej oblohe je prakticky neviditeľný.",
                "Nov Mesiaca nastáva {$when}. Tmavšia obloha zlepšuje podmienky na pozorovanie hmlovín, galaxií a hviezdokôp.",
                "V čase {$when} je Mesiac v nove. Ide o fázu s minimálnym mesačným jasom a priaznivým kontrastom pre slabé objekty.",
            ], [
                "Nov Mesiaca {$when}.",
                "Mesiac je v nove {$when}.",
                "Fáza novu Mesiaca {$when}.",
            ]);
        }

        if ($this->containsAny($normalized, ['prva stvrt mesiaca', 'first quarter moon'])) {
            return $this->finalizeFromVariants($event, 'moon.first_quarter', [
                "Mesiac dosahuje prvú štvrť {$when}. Pri večernom pozorovaní hranice medzi osvetlenou a tmavou časťou Mesiaca vyniknú detaily reliéfu.",
                "Prvá štvrť Mesiaca nastáva {$when}. Terminátor (hranica medzi svetlom a tieňom) zvýrazňuje štruktúru povrchu aj v menších ďalekohľadoch.",
                "V čase {$when} je Mesiac v prvej štvrti. Večerné pozorovanie často ponúka výrazný kontrast lunárnych detailov.",
            ], [
                "Prvá štvrť Mesiaca {$when}.",
                "Mesiac je v prvej štvrti {$when}.",
                "Fáza prvej štvrte Mesiaca {$when}.",
            ]);
        }

        if ($this->containsAny($normalized, ['posledna stvrt mesiaca', 'last quarter moon'])) {
            return $this->finalizeFromVariants($event, 'moon.last_quarter', [
                "Mesiac dosahuje poslednú štvrť {$when}. Pri rannom pozorovaní hranice medzi osvetlenou a tmavou časťou Mesiaca vyniknú plastické detaily povrchu.",
                "Posledná štvrť Mesiaca nastáva {$when}. Terminátor (hranica medzi svetlom a tieňom) zvýrazňuje reliéf najmä pri rannom pozorovaní.",
                "V čase {$when} je Mesiac v poslednej štvrti. Ranná obloha často ponúkne dobrý kontrast lunárnych útvarov.",
            ], [
                "Posledná štvrť Mesiaca {$when}.",
                "Mesiac je v poslednej štvrti {$when}.",
                "Fáza poslednej štvrte Mesiaca {$when}.",
            ]);
        }

        if (preg_match('/mesiac v perigeu:\s*([0-9][0-9\s.,]*)/iu', $title, $match) === 1) {
            $distance = $this->normalizeDistance((string) ($match[1] ?? ''));
            return $this->finalizeFromVariants($event, 'moon.perigee', [
                "Mesiac je v perigeu {$when} vo vzdialenosti približne {$distance} km od Zeme. Ide o bod dráhy s minimálnou geocentrickou vzdialenosťou.",
                "Perigeum Mesiaca nastáva {$when}, približne {$distance} km od Zeme. V tejto polohe má Mesiac väčší uhlový priemer než pri apogeu.",
            ], [
                "Mesiac v perigeu {$when}, vzdialenosť približne {$distance} km.",
                "Perigeum Mesiaca {$when}, asi {$distance} km od Zeme.",
            ]);
        }

        if (preg_match('/mesiac v apogeu:\s*([0-9][0-9\s.,]*)/iu', $title, $match) === 1) {
            $distance = $this->normalizeDistance((string) ($match[1] ?? ''));
            return $this->finalizeFromVariants($event, 'moon.apogee', [
                "Mesiac je v apogeu {$when} vo vzdialenosti približne {$distance} km od Zeme. Ide o bod dráhy s maximálnou geocentrickou vzdialenosťou.",
                "Apogeum Mesiaca nastáva {$when}, približne {$distance} km od Zeme. V tejto polohe má Mesiac menší uhlový priemer než pri perigeu.",
            ], [
                "Mesiac v apogeu {$when}, vzdialenosť približne {$distance} km.",
                "Apogeum Mesiaca {$when}, asi {$distance} km od Zeme.",
            ]);
        }

        if (preg_match('/mesiac pri (zostupnom|vzostupnom) uzle/iu', $title, $match) === 1) {
            $node = mb_strtolower((string) ($match[1] ?? ''), 'UTF-8') === 'zostupnom'
                ? 'zostupný'
                : 'vzostupný';
            return $this->finalizeFromVariants($event, 'moon.node', [
                "Mesiac prechádza {$node}m uzlom svojej dráhy {$when}. Uzly predstavujú priesečníky dráhy Mesiaca s rovinou ekliptiky.",
                "V čase {$when} je Mesiac pri {$node}m uzle. Ide o bod, v ktorom lunárna dráha pretína rovinu ekliptiky.",
            ], [
                "Mesiac je pri {$node}m uzle {$when}.",
                "{$node} uzol Mesiaca {$when}.",
            ]);
        }

        if (preg_match('/^(.+?)\s+([0-9]+(?:[.,][0-9]+)?)\x{00B0}/u', $title, $match) === 1
            && $this->containsAny($normalized, ['od mesiaca', 'of moon'])
        ) {
            $object = trim((string) ($match[1] ?? 'Objekt'));
            $angle = (string) ($match[2] ?? '');
            $direction = $this->resolveMoonOffsetDirection($title);
            $directionPart = $direction !== null ? " {$direction}" : '';

            return $this->finalizeFromVariants($event, 'moon.separation', [
                "{$object} bude {$when} vo vzdialenosti približne {$angle}°{$directionPart} od Mesiaca. Ide o malú uhlovú vzdialenosť vhodnú na spoločné pozorovanie oboch telies.",
                "V čase {$when} bude {$object} asi {$angle}°{$directionPart} od Mesiaca. Pri vhodných podmienkach môžu byť oba objekty pozorovateľné v jednom zornom poli ďalekohľadu.",
            ], [
                "{$object} je {$angle}°{$directionPart} od Mesiaca {$when}.",
                "Uhlová vzdialenosť {$object} od Mesiaca je asi {$angle}°{$directionPart} ({$when}).",
            ]);
        }

        if ($this->containsAny($normalized, ['zatmenie slnka', 'solar eclipse'])) {
            $magnitude = $this->extractMagnitude($title);
            $magnitudePart = $magnitude !== null ? " Uvedená magnitúda je {$magnitude}." : '';

            return $this->finalizeFromVariants($event, 'eclipse.solar', [
                "{$title} nastáva {$when} a má dosah {$region}. Pri pozorovaní Slnka používaj certifikovaný solárny filter.{$magnitudePart}",
                "{$title} je očakávané {$when} s dosahom {$region}. Pozorovanie Slnka je bezpečné iba cez certifikovaný solárny filter.{$magnitudePart}",
            ], [
                "{$title} {$when}.",
                "Zatmenie Slnka {$when}.",
            ]);
        }

        if ($this->containsAny($normalized, ['zatmenie mesiaca', 'lunar eclipse'])) {
            return $this->finalizeFromVariants($event, 'eclipse.lunar', [
                "{$title} nastáva {$when} a má dosah {$region}. Udalosť je bezpečná na priame pozorovanie aj bez špeciálneho filtra.",
                "{$title} je očakávané {$when} s dosahom {$region}. Na sledovanie zvyčajne nie je potrebný špeciálny filter.",
            ], [
                "{$title} {$when}.",
                "Zatmenie Mesiaca {$when}.",
            ]);
        }

        if ($this->containsAny($normalized, ['meteoricky roj', 'meteor shower', 'meteors'])) {
            return $this->finalizeFromVariants($event, 'meteor.shower', [
                "{$title} je aktívny {$when}. Najlepšie podmienky bývajú mimo mesta pri tmavej oblohe a po adaptácii zraku na tmu.",
                "Aktivita {$title} vrcholí okolo {$when}. Na pozorovanie je vhodné tmavé miesto bez rušivého osvetlenia.",
            ], [
                "{$title} {$when}.",
                "Meteorický roj {$title} {$when}.",
            ]);
        }

        if ($this->containsAny($normalized, ['opozicia', 'opposition'])) {
            return $this->finalizeFromVariants($event, 'planet.opposition', [
                "{$title} nastáva {$when}. Pri opozícii je teleso na oblohe oproti Slnku a obvykle je viditeľné väčšinu noci.",
                "{$title} je očakávaná {$when}. Opozícia zvyčajne prináša dobrú viditeľnosť objektu počas noci.",
            ], [
                "{$title} {$when}.",
                "Opozícia: {$title} ({$when}).",
            ]);
        }

        if ($this->containsAny($normalized, ['konjunkcia', 'conjunction'])) {
            return $this->finalizeFromVariants($event, 'planet.conjunction', [
                "{$title} nastáva {$when}. Konjunkcia znamená malú uhlovú vzdialenosť telies na oblohe pri pohľade zo Zeme.",
                "V čase {$when} nastáva {$title}. Ide o zdanlivé tesné priblíženie telies na oblohe.",
            ], [
                "{$title} {$when}.",
                "Konjunkcia: {$title} ({$when}).",
            ]);
        }

        if ($this->containsAny($normalized, ['perihel', 'afel', 'aphelion'])) {
            return $this->finalizeFromVariants($event, 'orbit.extreme', [
                "{$title} nastáva {$when}. Ide o bod dráhy, v ktorom je teleso od centrálneho telesa najbližšie alebo najďalej.",
                "V čase {$when} nastáva {$title}. Udalosť označuje extrémnu vzdialenosť na obežnej dráhe telesa.",
            ], [
                "{$title} {$when}.",
                "Dráhový bod: {$title} ({$when}).",
            ]);
        }

        if ($this->containsAny($normalized, ['elongac', 'najvacsej dlzke', 'greatest elongation'])) {
            return $this->finalizeFromVariants($event, 'elongation.greatest', [
                "{$title} nastáva {$when}. Pri najväčšej elongácii je uhlová vzdialenosť telesa od Slnka výrazná pre pozorovanie.",
                "{$title} je očakávaná {$when}. Väčšia elongácia často zlepšuje podmienky na pozorovanie objektu.",
            ], [
                "{$title} {$when}.",
                "Najväčšia elongácia: {$title} ({$when}).",
            ]);
        }

        return $this->finalizeFromVariants($event, 'generic.default', [
            "Udalosť {$title} nastáva {$when} a má dosah {$region}. Presná viditeľnosť závisí od polohy pozorovateľa a aktuálneho počasia.",
            "{$title} je očakávaná {$when} s dosahom {$region}. Podmienky pozorovania sa môžu líšiť podľa lokality a počasia.",
            "V čase {$when} prebehne {$title} s dosahom {$region}. To, ako dobre bude jav viditeľný, ovplyvní poloha aj počasie.",
        ], [
            "{$title} {$when}.",
            "{$title} – {$when}.",
            "Astronomická udalosť: {$title} ({$when}).",
        ]);
    }

    /**
     * @param array<int,string> $descriptionVariants
     * @param array<int,string> $shortVariants
     * @return array{description:string,short:string,provider:string}
     */
    private function finalizeFromVariants(
        Event $event,
        string $bucket,
        array $descriptionVariants,
        array $shortVariants
    ): array {
        return $this->finalize(
            description: $this->pickVariant($event, $bucket . '.description', $descriptionVariants),
            short: $this->pickVariant($event, $bucket . '.short', $shortVariants),
        );
    }

    /**
     * @param array<int,string> $variants
     */
    private function pickVariant(Event $event, string $bucket, array $variants): string
    {
        $normalized = array_values(array_filter(array_map(
            static fn (string $value): string => trim($value),
            $variants
        ), static fn (string $value): bool => $value !== ''));

        if ($normalized === []) {
            return '';
        }

        if (count($normalized) === 1) {
            return $normalized[0];
        }

        $seed = $this->buildVariantSeed($event, $bucket);
        $hash = hash('sha256', $seed);
        $number = hexdec(substr($hash, 0, 8));
        $index = (int) ($number % count($normalized));

        return $normalized[$index];
    }

    private function buildVariantSeed(Event $event, string $bucket): string
    {
        $parts = [
            $bucket,
            (string) ($event->id ?? ''),
            (string) ($event->source_uid ?? ''),
            (string) ($event->source_hash ?? ''),
            (string) ($event->title ?? ''),
            (string) ($event->type ?? ''),
            $this->seedValue($event->start_at ?? null),
            $this->seedValue($event->max_at ?? null),
            $this->seedValue($event->end_at ?? null),
        ];

        return implode('|', $parts);
    }

    private function seedValue(mixed $value): string
    {
        if ($value instanceof CarbonInterface) {
            return $value->clone()->utc()->toIso8601String();
        }

        return trim((string) $value);
    }

    /**
     * @return array{description:string,short:string,provider:string}
     */
    private function finalize(string $description, string $short): array
    {
        $normalizedDescription = $this->sanitize($description, 500);
        $normalizedShort = $this->sanitize($short, 180);

        if ($normalizedShort === '') {
            $normalizedShort = Str::limit($normalizedDescription, 180, '');
        }

        return [
            'description' => $normalizedDescription,
            'short' => $normalizedShort,
            'provider' => 'template',
        ];
    }

    private function sanitize(string $value, int $max): string
    {
        $plain = trim(strip_tags($value));
        if ($plain === '') {
            return '';
        }

        $plain = preg_replace('/\s+/u', ' ', $plain) ?? $plain;
        return Str::limit(trim($plain), $max, '');
    }

    private function normalizeTitle(string $title): string
    {
        $value = trim($title);
        if ($value === '') {
            return 'Astronomická udalosť';
        }

        $replace = [
            '/\bFULL MOON\b/iu' => 'spln',
            '/\bNEW MOON\b/iu' => 'nov',
            '/\bFIRST QUARTER MOON\b/iu' => 'prvá štvrť Mesiaca',
            '/\bLAST QUARTER MOON\b/iu' => 'posledná štvrť Mesiaca',
            '/\bat opoz[ií]cia\b/iu' => 'v opozícii',
            '/\bat perihelion\b/iu' => 'v perihéliu',
            '/\bat aphelion\b/iu' => 'v aféliu',
        ];

        foreach ($replace as $pattern => $to) {
            $value = preg_replace($pattern, $to, $value) ?? $value;
        }

        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        return trim($value);
    }

    private function normalizeForMatching(string $value): string
    {
        $ascii = Str::of($value)->ascii()->lower()->value();
        $ascii = preg_replace('/\s+/u', ' ', $ascii) ?? $ascii;
        return trim($ascii);
    }

    /**
     * @param array<int,string> $needles
     */
    private function containsAny(string $value, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($value, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function resolveMoment(Event $event): ?CarbonInterface
    {
        foreach (['start_at', 'max_at', 'end_at'] as $field) {
            $value = $event->{$field} ?? null;
            if ($value instanceof CarbonInterface) {
                return $value;
            }
        }

        return null;
    }

    private function formatWhen(?CarbonInterface $moment): string
    {
        if (! $moment instanceof CarbonInterface) {
            return 'v neurčenom čase';
        }

        $tz = (string) config('events.timezone', 'Europe/Bratislava');
        return $moment->clone()->setTimezone($tz)->format('d. m. Y \o H:i');
    }

    private function regionPhrase(string $regionScope): string
    {
        return match (strtolower(trim($regionScope))) {
            'sk' => 'na Slovensku',
            'eu' => 'v Európe',
            default => 'globálne',
        };
    }

    private function normalizeDistance(string $value): string
    {
        $text = preg_replace('/\s+/u', '', trim($value)) ?? trim($value);
        $text = str_replace(',', '.', $text);

        if (! is_numeric($text)) {
            return trim($value);
        }

        return number_format((float) $text, 0, '', ' ');
    }

    private function resolveMoonOffsetDirection(string $title): ?string
    {
        if (preg_match('/\x{00B0}\s*N\b/u', $title) === 1) {
            return 'severne';
        }
        if (preg_match('/\x{00B0}\s*S\b/u', $title) === 1) {
            return 'južne';
        }
        if (preg_match('/s\.\s*š\./iu', $title) === 1) {
            return 'severne';
        }
        if (preg_match('/j\.\s*š\./iu', $title) === 1) {
            return 'južne';
        }

        return null;
    }

    private function extractMagnitude(string $title): ?string
    {
        if (preg_match('/mag\s*=\s*([0-9]+(?:[.,][0-9]+)?)/iu', $title, $match) !== 1) {
            return null;
        }

        return (string) ($match[1] ?? null);
    }
}

