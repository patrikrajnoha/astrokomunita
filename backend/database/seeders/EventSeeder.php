<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Event;

class EventSeeder extends Seeder
{
    public function run(): void
    {
        // Pozor: ak by si mal FK v budúcnosti, potom radšej delete() než truncate()
        Event::truncate();

        Event::create([
            'title' => 'Kvadrantidy',
            'type' => 'meteors',
            'max_at' => '2026-01-04 03:40:00',
            'short' => 'Silný meteorický roj s maximom v skorých ranných hodinách.',
            'description' => 'Najlepšie pozorovanie je po polnoci až do svitania. Odporúča sa tmavé miesto mimo mesta.',
            'visibility' => 'Slovensko',
        ]);

        Event::create([
            'title' => 'Čiastočné zatmenie Mesiaca',
            'type' => 'eclipse',
            'max_at' => '2026-03-03 21:10:00',
            'short' => 'Mesiac bude čiastočne v zemskom tieni, pozorovateľné vo večerných hodinách.',
            'description' => 'Pozorovateľnosť závisí od počasia a výhľadu na oblohu. Voľným okom, ďalekohľad voliteľný.',
            'visibility' => 'Európa',
        ]);

        Event::create([
            'title' => 'Venuša a Jupiter blízko seba',
            'type' => 'conjunction',
            'max_at' => '2026-04-11 20:30:00',
            'short' => 'Planéty budú na oblohe veľmi blízko – vhodné aj na pozorovanie voľným okom.',
            'description' => 'Konjunkcia je zdanlivé priblíženie telies na oblohe. Výborné na fotenie, stačí aj mobil.',
            'visibility' => 'Slovensko',
        ]);
    }
}
