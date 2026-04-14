<?php

namespace Database\Seeders;

use App\Models\Event;
use Illuminate\Database\Seeder;

class DemoEventsSeeder extends Seeder
{
    public function run(): void
    {
        $events = [
            [
                'title' => 'Meteorický roj Perzeidy',
                'type' => 'meteors',
                'region_scope' => 'sk',
                'start_at' => now()->addDays(5)->setTime(22, 0),
                'end_at' => now()->addDays(6)->setTime(4, 0),
                'max_at' => now()->addDays(6)->setTime(1, 0),
                'short' => 'Pozorovanie je najlepšie po polnoci mimo mesta.',
                'description' => 'Udalosť je seedovaná ako demo dáta pre iniciálne naplnenie prehľadu udalostí.',
                'visibility' => 1,
                'source_name' => 'manual',
                'source_uid' => 'demo-perseidy',
            ],
            [
                'title' => 'Čiastočné zatmenie Mesiaca',
                'type' => 'eclipse',
                'region_scope' => 'eu',
                'start_at' => now()->addDays(12)->setTime(20, 30),
                'end_at' => now()->addDays(12)->setTime(23, 10),
                'max_at' => now()->addDays(12)->setTime(21, 50),
                'short' => 'Viditeľné voľným okom pri jasnej oblohe.',
                'description' => 'Ukážková udalosť pre testovanie detailu eventu a notifikácií.',
                'visibility' => 1,
                'source_name' => 'manual',
                'source_uid' => 'demo-zatmenie-mesiaca',
            ],
            [
                'title' => 'Konjunkcia Venuše a Jupitera',
                'type' => 'conjunction',
                'region_scope' => 'global',
                'start_at' => now()->addDays(20)->setTime(19, 45),
                'end_at' => now()->addDays(20)->setTime(21, 15),
                'max_at' => now()->addDays(20)->setTime(20, 15),
                'short' => 'Dve jasné planéty blízko pri sebe na večernej oblohe.',
                'description' => 'Demo dáta pre feed udalostí; source_name/source_uid spĺňajú podmienky publikovania.',
                'visibility' => 1,
                'source_name' => 'manual',
                'source_uid' => 'demo-venusa-jupiter',
            ],
        ];

        foreach ($events as $event) {
            Event::query()->updateOrCreate(
                ['source_name' => $event['source_name'], 'source_uid' => $event['source_uid']],
                $event
            );
        }
    }
}
