<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\SidebarSection;

class SidebarSectionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sections = [
            [
                'key' => 'next_event',
                'title' => 'Najbližšia udalosť',
                'is_visible' => true,
                'sort_order' => 1,
            ],
            [
                'key' => 'latest_articles',
                'title' => 'Najnovšie články',
                'is_visible' => true,
                'sort_order' => 2,
            ],
            [
                'key' => 'nasa_apod',
                'title' => 'NASA – Obrázok dňa',
                'is_visible' => true,
                'sort_order' => 3,
            ],
        ];

        foreach ($sections as $section) {
            SidebarSection::firstOrCreate(
                ['key' => $section['key']],
                $section
            );
        }
    }
}
