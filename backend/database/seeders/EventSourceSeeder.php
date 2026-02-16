<?php

namespace Database\Seeders;

use App\Enums\EventSource;
use App\Models\EventSource as EventSourceModel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

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
            $model = EventSourceModel::query()->updateOrCreate(
                ['key' => $row['key']],
                $row
            );

            DB::table('event_candidates')
                ->where('source_name', $row['key'])
                ->whereNull('event_source_id')
                ->update([
                    'event_source_id' => $model->id,
                    'external_id' => DB::raw('COALESCE(external_id, source_uid)'),
                ]);

            DB::table('crawl_runs')
                ->where('source_name', $row['key'])
                ->whereNull('event_source_id')
                ->update(['event_source_id' => $model->id]);
        }

        EventSourceModel::query()->where('key', 'go_astronomy')->delete();
    }
}
