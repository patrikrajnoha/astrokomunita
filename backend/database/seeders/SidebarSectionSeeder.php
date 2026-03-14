<?php

namespace Database\Seeders;

use App\Models\SidebarSection;
use Illuminate\Database\Seeder;

class SidebarSectionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sections = [
            [
                'key' => 'observing_conditions',
                'title' => 'Astronomicke podmienky',
                'is_visible' => true,
                'sort_order' => 0,
            ],
            [
                'key' => 'observing_weather',
                'title' => 'Pocasie pre pozorovanie',
                'is_visible' => true,
                'sort_order' => 1,
            ],
            [
                'key' => 'night_sky',
                'title' => 'Nocna obloha',
                'is_visible' => true,
                'sort_order' => 2,
            ],
            [
                'key' => 'iss_pass',
                'title' => 'ISS prelet',
                'is_visible' => true,
                'sort_order' => 3,
            ],
            [
                'key' => 'search',
                'title' => 'Search',
                'is_visible' => true,
                'sort_order' => 4,
            ],
            [
                'key' => 'nasa_apod',
                'title' => 'NASA Novinky',
                'is_visible' => true,
                'sort_order' => 5,
            ],
            [
                'key' => 'next_event',
                'title' => 'Najblizsia udalost',
                'is_visible' => true,
                'sort_order' => 6,
            ],
            [
                'key' => 'latest_articles',
                'title' => 'Najnovsie clanky',
                'is_visible' => true,
                'sort_order' => 7,
            ],
            [
                'key' => 'upcoming_events',
                'title' => 'Co sa deje',
                'is_visible' => true,
                'sort_order' => 8,
            ],
            [
                'key' => 'moon_phases',
                'title' => 'Fazy mesiaca',
                'is_visible' => true,
                'sort_order' => 9,
            ],
            [
                'key' => 'moon_overview',
                'title' => 'Mesiac teraz',
                'is_visible' => true,
                'sort_order' => 10,
            ],
            [
                'key' => 'moon_events',
                'title' => 'Lunarne udalosti',
                'is_visible' => true,
                'sort_order' => 11,
            ],
        ];

        foreach ($sections as $section) {
            SidebarSection::updateOrCreate(
                ['key' => $section['key']],
                $section
            );
        }

        SidebarSection::query()->where('key', 'sky_tonight')->delete();
    }
}
