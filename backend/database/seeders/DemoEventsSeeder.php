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
                'title' => 'Meteoricky roj Perseidy',
                'type' => 'meteors',
                'region_scope' => 'sk',
                'start_at' => now()->addDays(5)->setTime(22, 0),
                'end_at' => now()->addDays(6)->setTime(4, 0),
                'max_at' => now()->addDays(6)->setTime(1, 0),
                'short' => 'Pozorovanie najlepsi po polnoci mimo mesta.',
                'description' => 'Udalost je seedovana ako demo data pre inicialne naplnenie prehladu udalosti.',
                'visibility' => 1,
                'source_name' => 'manual',
                'source_uid' => 'demo-perseidy',
            ],
            [
                'title' => 'Ciastocne zatmenie Mesiaca',
                'type' => 'eclipse',
                'region_scope' => 'eu',
                'start_at' => now()->addDays(12)->setTime(20, 30),
                'end_at' => now()->addDays(12)->setTime(23, 10),
                'max_at' => now()->addDays(12)->setTime(21, 50),
                'short' => 'Viditelne volnym okom pri jasnej oblohe.',
                'description' => 'Ukazkova udalost pre testovanie detailu eventu a notifikacii.',
                'visibility' => 1,
                'source_name' => 'manual',
                'source_uid' => 'demo-zatmenie-mesiaca',
            ],
            [
                'title' => 'Konjunkcia Venuse a Jupitera',
                'type' => 'conjunction',
                'region_scope' => 'global',
                'start_at' => now()->addDays(20)->setTime(19, 45),
                'end_at' => now()->addDays(20)->setTime(21, 15),
                'max_at' => now()->addDays(20)->setTime(20, 15),
                'short' => 'Dve jasne planety blizko pri sebe na vecernej oblohe.',
                'description' => 'Demo data pre feed udalosti; source_name/source_uid splnaju podmienky publikovania.',
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
