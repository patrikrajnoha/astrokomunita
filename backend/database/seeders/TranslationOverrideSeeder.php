<?php

namespace Database\Seeders;

use App\Models\TranslationOverride;
use Illuminate\Database\Seeder;

class TranslationOverrideSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['source_term' => 'meteor shower', 'target_term' => 'meteoricky roj'],
            ['source_term' => 'occultation', 'target_term' => 'zakryt'],
            ['source_term' => 'conjunction', 'target_term' => 'konjunkcia'],
            ['source_term' => 'opposition', 'target_term' => 'opozicia'],
            ['source_term' => 'equinox', 'target_term' => 'rovnodennost'],
            ['source_term' => 'solstice', 'target_term' => 'slnovrat'],
            ['source_term' => 'full moon', 'target_term' => 'spln'],
            ['source_term' => 'new moon', 'target_term' => 'nov'],
            ['source_term' => 'first quarter moon', 'target_term' => 'prva stvrt'],
            ['source_term' => 'last quarter moon', 'target_term' => 'posledna stvrt'],
            ['source_term' => 'moon at', 'target_term' => 'mesiac pri'],
            ['source_term' => 'sun', 'target_term' => 'slnko'],
        ];

        foreach ($rows as $row) {
            TranslationOverride::query()->updateOrCreate(
                [
                    'source_term' => $row['source_term'],
                    'language_from' => 'en',
                    'language_to' => 'sk',
                    'is_case_sensitive' => false,
                ],
                [
                    'target_term' => $row['target_term'],
                ]
            );
        }
    }
}
