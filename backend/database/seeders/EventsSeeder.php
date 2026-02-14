<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class EventsSeeder extends Seeder
{
    public function run(): void
    {
        // Vyčistenie tabuliek (aby si sa zbavil starých "falošných" dát)
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('favorites')->truncate();
        DB::table('events')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $events = [
            // ===== ECLIPSES (overené UTC časy - maximum) =====
            [
                'title' => 'Prstencové zatmenie Slnka',
                'type' => 'eclipse',
                // Maximum Eclipse: 2026-02-17 12:12:04 UTC
                'max_at' => Carbon::parse('2026-02-17 12:12:04', 'UTC'),
                'short' => 'Prstencové zatmenie Slnka (annular) – maximum 12:12 UTC.',
                'description' => 'Prstencové zatmenie nastáva, keď Mesiac zakryje stred Slnka, ale ponechá viditeľný jasný prstenec. Pozorovanie len s bezpečnou ochranou zraku.',
                'visibility' => 1,
            ],
            [
                'title' => 'Úplné zatmenie Mesiaca',
                'type' => 'eclipse',
                // Maximum Eclipse: 2026-03-03 11:33:46 UTC
                'max_at' => Carbon::parse('2026-03-03 11:33:46', 'UTC'),
                'short' => 'Úplné zatmenie Mesiaca (“krvavý Mesiac”) – maximum 11:33 UTC.',
                'description' => 'Mesiac prechádza úplne do zemského tieňa; počas totality môže získať červený/oranžový odtieň.',
                'visibility' => 1,
            ],
            [
                'title' => 'Úplné zatmenie Slnka',
                'type' => 'eclipse',
                // Maximum Eclipse: 2026-08-12 17:46:06 UTC
                'max_at' => Carbon::parse('2026-08-12 17:46:06', 'UTC'),
                'short' => 'Úplné zatmenie Slnka – maximum 17:46 UTC.',
                'description' => 'Mesiac úplne zakryje slnečný disk a na krátky čas sa zviditeľní koróna. Pozorovať len s vhodnou ochranou.',
                'visibility' => 1,
            ],
            [
                'title' => 'Čiastočné zatmenie Mesiaca',
                'type' => 'eclipse',
                // Maximum Eclipse: 2026-08-28 04:12:53 UTC
                'max_at' => Carbon::parse('2026-08-28 04:12:53', 'UTC'),
                'short' => 'Hlboké čiastočné zatmenie Mesiaca – maximum 04:12 UTC.',
                'description' => 'Časť disku Mesiaca sa ponorí do tieňa Zeme; viditeľnosť závisí od lokality.',
                'visibility' => 1,
            ],

            // ===== METEOR SHOWERS (peak nights – dátumovo overené) =====
            [
                'title' => 'Kvadrantidy',
                'type' => 'meteors',
                // Peak night Jan 3–4, 2026 (čas peak-u sa líši; ukladám "stred" noci UTC)
                'max_at' => Carbon::parse('2026-01-03 23:30:00', 'UTC'),
                'short' => 'Peak noc 3–4. januára (Kvadrantidy).',
                'description' => 'Kvadrantidy mávajú krátke, ale výrazné maximum. Najlepšie pozorovanie v noci / nadránom podľa lokality.',
                'visibility' => 1,
            ],
            [
                'title' => 'Eta Akvaridy',
                'type' => 'meteors',
                // Peak night May 5–6, 2026
                'max_at' => Carbon::parse('2026-05-06 00:00:00', 'UTC'),
                'short' => 'Peak noc 5–6. mája (Eta Akvaridy).',
                'description' => 'Roj spojený s Halleyho kométou. Najlepšie pozorovanie typicky pred svitaním.',
                'visibility' => 1,
            ],
            [
                'title' => 'Perseidy',
                'type' => 'meteors',
                // Peak night Aug 12–13, 2026
                'max_at' => Carbon::parse('2026-08-13 00:00:00', 'UTC'),
                'short' => 'Peak noc 12–13. augusta (Perseidy).',
                'description' => 'Perseidy sú jedny z najpopulárnejších rojov roka, často s jasnými meteor(mi).',
                'visibility' => 1,
            ],
            [
                'title' => 'Leonidy',
                'type' => 'meteors',
                // Peak night Nov 17–18, 2026
                'max_at' => Carbon::parse('2026-11-18 00:00:00', 'UTC'),
                'short' => 'Peak noc 17–18. novembra (Leonidy).',
                'description' => 'Leonidy môžu byť veľmi variabilné; v niektorých rokoch sú známe “meteorickými búrkami”.',
                'visibility' => 1,
            ],
            [
                'title' => 'Geminidy',
                'type' => 'meteors',
                // (V praxi je peak noc 13–14 decembra; ak chceš, upravím po ďalšom kroku)
                'max_at' => Carbon::parse('2026-12-14 00:00:00', 'UTC'),
                'short' => 'Peak noc okolo polovice decembra (Geminidy).',
                'description' => 'Geminidy patria medzi najsilnejšie roje roka; často prinášajú vysokú aktivitu.',
                'visibility' => 1,
            ],

            // ===== PLANETS / ALIGNMENTS =====
            [
                'title' => 'Opozícia Jupiteru',
                'type' => 'planet',
                // Opposition date: 2026-01-10
                'max_at' => Carbon::parse('2026-01-10 00:00:00', 'UTC'),
                'short' => 'Jupiter je pri opozícii najjasnejší a viditeľný celú noc (10. januára 2026).',
                'description' => 'Počas opozície je Jupiter veľmi vhodný na pozorovanie – aj malý ďalekohľad ukáže pásy a Galileove mesiace.',
                'visibility' => 1,
            ],
            [
                'title' => 'Výrazné planetárne konjunkcie',
                'type' => 'conjunction',
                // Konjunkcie sú lokálne závislé; dáme “placeholder” na konkrétny deň známeho zoskupenia
                'max_at' => Carbon::parse('2026-06-09 00:00:00', 'UTC'),
                'short' => 'V roku 2026 nastanú viaceré výrazné zoskupenia (konjunkcie); presný vzhľad závisí od lokality.',
                'description' => 'Konjunkcie sú vizuálne atraktívne (vhodné aj na fotenie). Ak chceš, v ďalšom kroku ich rozdelíme na konkrétne páry planét a doplníme presné časy.',
                'visibility' => 1,
            ],

            // ===== NASA / MISSION =====
            [
                'title' => 'Artemis II – pilotovaný let k Mesiacu',
                'type' => 'mission',
                // Artemis II nemá stabilný "pevný" dátum; NASA uvádza "No later than April 2026" a médiá riešia okno vo februári.
                // Do DB dávam "target" na začiatok okna – budeš to vedieť neskôr aktualizovať.
                'max_at' => Carbon::parse('2026-02-06 00:00:00', 'UTC'),
                'short' => 'Misia Artemis II – plánovaný pilotovaný let (dátum je cieľový a môže sa meniť).',
                'description' => 'Artemis II je pilotovaná misia programu Artemis (Orion + SLS). Termín je predmetom aktualizácií podľa pripravenosti.',
                'visibility' => 1,
            ],
        ];

        $events = array_map(static function (array $event): array {
            if (!array_key_exists('region_scope', $event)) {
                $event['region_scope'] = 'global';
            }

            return $event;
        }, $events);

        DB::table('events')->insert($events);
    }
}
