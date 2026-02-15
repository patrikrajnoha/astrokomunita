<?php

namespace Database\Seeders;

use App\Enums\EventSource;
use App\Models\EventSource as EventSourceModel;
use Illuminate\Database\Seeder;

class EventSourceSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            [
                'key' => EventSource::ASTROPIXELS->value,
                'name' => EventSource::ASTROPIXELS->label(),
                'base_url' => 'https://astropixels.com/almanac/almanac21/',
                'is_enabled' => true,
            ],
            [
                'key' => EventSource::NASA->value,
                'name' => EventSource::NASA->label(),
                'base_url' => 'https://www.nasa.gov/',
                'is_enabled' => true,
            ],
        ];

        foreach ($rows as $row) {
            EventSourceModel::query()->updateOrCreate(
                ['key' => $row['key']],
                $row
            );
        }
    }
}
