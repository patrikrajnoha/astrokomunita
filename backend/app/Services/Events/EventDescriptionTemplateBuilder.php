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
            return $this->finalize(
                description: "Mesiac dosahuje fázu splnu {$when}. Najjasnejšia mesačná noc môže znížiť kontrast slabých objektov na oblohe.",
                short: "Spln Mesiaca {$when}.",
            );
        }

        if ($this->containsAny($normalized, ['nov', 'new moon'])) {
            return $this->finalize(
                description: "Mesiac dosahuje fázu novu {$when}. Obloha býva tmavšia, preto je vhodný čas na pozorovanie slabých objektov mimo mesta.",
                short: "Nov Mesiaca {$when}.",
            );
        }

        if ($this->containsAny($normalized, ['prva stvrt mesiaca', 'first quarter moon'])) {
            return $this->finalize(
                description: "Mesiac dosahuje prvú štvrť {$when}. Pri večernom pozorovaní hranice medzi osvetlenou a tmavou časťou Mesiaca vyniknú detaily reliéfu aj v menších ďalekohľadoch.",
                short: "Prvá štvrť Mesiaca {$when}.",
            );
        }

        if ($this->containsAny($normalized, ['posledna stvrt mesiaca', 'last quarter moon'])) {
            return $this->finalize(
                description: "Mesiac dosahuje poslednú štvrť {$when}. Pri rannom pozorovaní hranice medzi osvetlenou a tmavou časťou Mesiaca vyniknú plastické detaily povrchu.",
                short: "Posledná štvrť Mesiaca {$when}.",
            );
        }

        if (preg_match('/mesiac v perigeu:\s*([0-9][0-9\s.,]*)/iu', $title, $match) === 1) {
            $distance = $this->normalizeDistance((string) ($match[1] ?? ''));
            return $this->finalize(
                description: "Mesiac je v perigeu {$when} vo vzdialenosti približne {$distance} km od Zeme. Ide o bod dráhy, kde je Mesiac k Zemi najbližšie.",
                short: "Mesiac v perigeu {$when}, vzdialenosť približne {$distance} km.",
            );
        }

        if (preg_match('/mesiac v apogeu:\s*([0-9][0-9\s.,]*)/iu', $title, $match) === 1) {
            $distance = $this->normalizeDistance((string) ($match[1] ?? ''));
            return $this->finalize(
                description: "Mesiac je v apogeu {$when} vo vzdialenosti približne {$distance} km od Zeme. Ide o bod dráhy, kde je Mesiac od Zeme najďalej.",
                short: "Mesiac v apogeu {$when}, vzdialenosť približne {$distance} km.",
            );
        }

        if (preg_match('/mesiac pri (zostupnom|vzostupnom) uzle/iu', $title, $match) === 1) {
            $node = mb_strtolower((string) ($match[1] ?? ''), 'UTF-8') === 'zostupnom'
                ? 'zostupný'
                : 'vzostupný';
            return $this->finalize(
                description: "Mesiac prechádza cez {$node} uzol svojej dráhy {$when}. Uzly sú body, kde dráha Mesiaca pretína rovinu ekliptiky.",
                short: "Mesiac je pri {$node}m uzle {$when}.",
            );
        }

        if (preg_match('/^(.+?)\s+([0-9]+(?:[.,][0-9]+)?)\x{00B0}/u', $title, $match) === 1
            && $this->containsAny($normalized, ['od mesiaca', 'of moon'])
        ) {
            $object = trim((string) ($match[1] ?? 'Objekt'));
            $angle = (string) ($match[2] ?? '');
            $direction = $this->resolveMoonOffsetDirection($title);
            $directionPart = $direction !== null ? " {$direction}" : '';

            return $this->finalize(
                description: "{$object} bude {$when} približne {$angle}°{$directionPart} od Mesiaca. Ide o tesné uhlové priblíženie vhodné na vizuálne pozorovanie.",
                short: "{$object} je {$angle}°{$directionPart} od Mesiaca {$when}.",
            );
        }

        if ($this->containsAny($normalized, ['zatmenie slnka', 'solar eclipse'])) {
            $magnitude = $this->extractMagnitude($title);
            $magnitudePart = $magnitude !== null ? " Uvedená magnitúda je {$magnitude}." : '';

            return $this->finalize(
                description: "{$title} nastáva {$when} a má dosah {$region}. Pri pozorovaní Slnka používaj certifikovaný solárny filter.{$magnitudePart}",
                short: "{$title} {$when}.",
            );
        }

        if ($this->containsAny($normalized, ['zatmenie mesiaca', 'lunar eclipse'])) {
            return $this->finalize(
                description: "{$title} nastáva {$when} a má dosah {$region}. Udalosť je bezpečná na priame pozorovanie aj bez špeciálneho filtra.",
                short: "{$title} {$when}.",
            );
        }

        if ($this->containsAny($normalized, ['meteoricky roj', 'meteor shower', 'meteors'])) {
            return $this->finalize(
                description: "{$title} je aktívny {$when}. Najlepšie podmienky bývajú mimo mesta pri tmavej oblohe a po adaptácii zraku na tmu.",
                short: "{$title} {$when}.",
            );
        }

        if ($this->containsAny($normalized, ['opozicia', 'opposition'])) {
            return $this->finalize(
                description: "{$title} nastáva {$when}. Pri opozícii je teleso na oblohe oproti Slnku a obvykle je viditeľné väčšinu noci.",
                short: "{$title} {$when}.",
            );
        }

        if ($this->containsAny($normalized, ['konjunkcia', 'conjunction'])) {
            return $this->finalize(
                description: "{$title} nastáva {$when}. Konjunkcia znamená malú uhlovú vzdialenosť telies na oblohe pri pohľade zo Zeme.",
                short: "{$title} {$when}.",
            );
        }

        if ($this->containsAny($normalized, ['perihel', 'afel', 'aphelion'])) {
            return $this->finalize(
                description: "{$title} nastáva {$when}. Ide o bod dráhy, v ktorom je teleso od centrálneho telesa najbližšie alebo najďalej.",
                short: "{$title} {$when}.",
            );
        }

        if ($this->containsAny($normalized, ['elongac', 'najvacsej dlzke', 'greatest elongation'])) {
            return $this->finalize(
                description: "{$title} nastáva {$when}. Pri najväčšej elongácii je uhlová vzdialenosť telesa od Slnka výrazná pre pozorovanie.",
                short: "{$title} {$when}.",
            );
        }

        return $this->finalize(
            description: "Udalosť {$title} nastáva {$when} a má dosah {$region}. Presná viditeľnosť závisí od polohy pozorovateľa a aktuálneho počasia.",
            short: "{$title} {$when}.",
        );
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
