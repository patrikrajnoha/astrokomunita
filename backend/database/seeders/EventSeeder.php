<?php

namespace Database\Seeders;

use App\Models\Event;
use Illuminate\Database\Seeder;

class EventSeeder extends Seeder
{
    public function run(): void
    {
        Event::truncate();

        Event::create([
            'title' => 'Kvadrantidy',
            'type' => 'meteors',
            'region_scope' => 'sk',
            'max_at' => '2026-01-04 03:40:00',
            'short' => 'Silný meteorický roj s maximom v ranných hodinách.',
            'description' => 'Najlepšie pozorovanie je po polnoci až do svitania.',
            'visibility' => 1,
            'source_name' => 'manual',
            'source_uid' => 'seed-kvadrantidy',
        ]);

        Event::create([
            'title' => 'Čiastočné zatmenie Mesiaca',
            'type' => 'eclipse',
            'region_scope' => 'eu',
            'max_at' => '2026-03-03 21:10:00',
            'short' => 'Večerne pozorovateľné z väčšiny Európy.',
            'description' => 'Pozorovateľnosť závisí od počasia a výhľadu na oblohu.',
            'visibility' => 1,
            'source_name' => 'manual',
            'source_uid' => 'seed-zatmenie-mesiaca',
        ]);

        Event::create([
            'title' => 'Venuša a Jupiter blízko seba',
            'type' => 'conjunction',
            'region_scope' => 'global',
            'max_at' => '2026-04-11 20:30:00',
            'short' => 'Planéty budú na oblohe veľmi blízko.',
            'description' => 'Konjunkcia je vhodná aj na fotenie mobilom.',
            'visibility' => 1,
            'source_name' => 'manual',
            'source_uid' => 'seed-venusa-jupiter',
        ]);
    }
}
