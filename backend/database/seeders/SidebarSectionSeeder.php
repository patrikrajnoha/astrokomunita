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
                'key' => 'search',
                'title' => 'Search',
                'is_visible' => true,
                'sort_order' => 0,
            ],
            [
                'key' => 'observing_conditions',
                'title' => 'Observing Conditions',
                'is_visible' => true,
                'sort_order' => 1,
            ],
            [
                'key' => 'next_event',
                'title' => 'Najblizsia udalost',
                'is_visible' => true,
                'sort_order' => 2,
            ],
            [
                'key' => 'latest_articles',
                'title' => 'Najnovsie clanky',
                'is_visible' => true,
                'sort_order' => 3,
            ],
            [
                'key' => 'nasa_apod',
                'title' => 'NASA obrazok dna',
                'is_visible' => true,
                'sort_order' => 4,
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
