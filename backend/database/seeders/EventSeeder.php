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
            'short' => 'Silny meteoricky roj s maximom v rannych hodinach.',
            'description' => 'Najlepsie pozorovanie je po polnoci az do svitania.',
            'visibility' => 1,
            'source_name' => 'manual',
            'source_uid' => 'seed-kvadrantidy',
        ]);

        Event::create([
            'title' => 'Ciastocne zatmenie Mesiaca',
            'type' => 'eclipse',
            'region_scope' => 'eu',
            'max_at' => '2026-03-03 21:10:00',
            'short' => 'Vecerne pozorovatelne z vacsiny Europy.',
            'description' => 'Pozorovatelnost zavisi od pocasia a vyhladu na oblohu.',
            'visibility' => 1,
            'source_name' => 'manual',
            'source_uid' => 'seed-zatmenie-mesiaca',
        ]);

        Event::create([
            'title' => 'Venusa a Jupiter blizko seba',
            'type' => 'conjunction',
            'region_scope' => 'global',
            'max_at' => '2026-04-11 20:30:00',
            'short' => 'Planety budu na oblohe velmi blizko.',
            'description' => 'Konjunkcia je vhodna aj na fotenie mobilom.',
            'visibility' => 1,
            'source_name' => 'manual',
            'source_uid' => 'seed-venusa-jupiter',
        ]);
    }
}
