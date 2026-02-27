<?php

namespace Database\Seeders;

use App\Models\TranslationOverride;
use Illuminate\Database\Seeder;

class TranslationOverrideSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            // EN -> SK terminology guards (before + after translation)
            ['source_term' => 'meteor shower', 'target_term' => 'meteorický roj', 'language_from' => 'en', 'language_to' => 'sk'],
            ['source_term' => 'occultation', 'target_term' => 'zákryt', 'language_from' => 'en', 'language_to' => 'sk'],
            ['source_term' => 'conjunction', 'target_term' => 'konjunkcia', 'language_from' => 'en', 'language_to' => 'sk'],
            ['source_term' => 'opposition', 'target_term' => 'opozícia', 'language_from' => 'en', 'language_to' => 'sk'],
            ['source_term' => 'equinox', 'target_term' => 'rovnodennosť', 'language_from' => 'en', 'language_to' => 'sk'],
            ['source_term' => 'solstice', 'target_term' => 'slnovrat', 'language_from' => 'en', 'language_to' => 'sk'],
            ['source_term' => 'full moon', 'target_term' => 'spln', 'language_from' => 'en', 'language_to' => 'sk'],
            ['source_term' => 'new moon', 'target_term' => 'nov', 'language_from' => 'en', 'language_to' => 'sk'],
            ['source_term' => 'first quarter moon', 'target_term' => 'prvá štvrť Mesiaca', 'language_from' => 'en', 'language_to' => 'sk'],
            ['source_term' => 'last quarter moon', 'target_term' => 'posledná štvrť Mesiaca', 'language_from' => 'en', 'language_to' => 'sk'],
            ['source_term' => 'first quarter', 'target_term' => 'prvá štvrť', 'language_from' => 'en', 'language_to' => 'sk'],
            ['source_term' => 'last quarter', 'target_term' => 'posledná štvrť', 'language_from' => 'en', 'language_to' => 'sk'],
            ['source_term' => 'moon at', 'target_term' => 'Mesiac pri', 'language_from' => 'en', 'language_to' => 'sk'],
            ['source_term' => 'moon', 'target_term' => 'Mesiac', 'language_from' => 'en', 'language_to' => 'sk'],
            ['source_term' => 'of moon', 'target_term' => 'od Mesiaca', 'language_from' => 'en', 'language_to' => 'sk'],
            ['source_term' => 'descending node', 'target_term' => 'zostupný uzol', 'language_from' => 'en', 'language_to' => 'sk'],
            ['source_term' => 'ascending node', 'target_term' => 'vzostupný uzol', 'language_from' => 'en', 'language_to' => 'sk'],
            ['source_term' => 'annular solar eclipse', 'target_term' => 'prstencové zatmenie Slnka', 'language_from' => 'en', 'language_to' => 'sk'],
            ['source_term' => 'partial solar eclipse', 'target_term' => 'čiastočné zatmenie Slnka', 'language_from' => 'en', 'language_to' => 'sk'],
            ['source_term' => 'total solar eclipse', 'target_term' => 'úplné zatmenie Slnka', 'language_from' => 'en', 'language_to' => 'sk'],
            ['source_term' => 'lunar eclipse', 'target_term' => 'zatmenie Mesiaca', 'language_from' => 'en', 'language_to' => 'sk'],
            ['source_term' => 'at apogee', 'target_term' => 'v apogeu', 'language_from' => 'en', 'language_to' => 'sk'],
            ['source_term' => 'at perigee', 'target_term' => 'v perigeu', 'language_from' => 'en', 'language_to' => 'sk'],
            ['source_term' => 'apogee', 'target_term' => 'apogeu', 'language_from' => 'en', 'language_to' => 'sk'],
            ['source_term' => 'perigee', 'target_term' => 'perigeu', 'language_from' => 'en', 'language_to' => 'sk'],
            ['source_term' => 'sun', 'target_term' => 'Slnko', 'language_from' => 'en', 'language_to' => 'sk'],

            // SK -> SK post-correction rules for noisy machine outputs
            ['source_term' => 'prvý štvrt Mesiac', 'target_term' => 'prvá štvrť Mesiaca', 'language_from' => 'sk', 'language_to' => 'sk'],
            ['source_term' => 'prvy stvrt Mesiac', 'target_term' => 'prvá štvrť Mesiaca', 'language_from' => 'sk', 'language_to' => 'sk'],
            ['source_term' => 'prvy stvrt mesiac', 'target_term' => 'prvá štvrť Mesiaca', 'language_from' => 'sk', 'language_to' => 'sk'],
            ['source_term' => 'prva stvrt', 'target_term' => 'prvá štvrť Mesiaca', 'language_from' => 'sk', 'language_to' => 'sk'],
            ['source_term' => 'posledný štvrt Mesiac', 'target_term' => 'posledná štvrť Mesiaca', 'language_from' => 'sk', 'language_to' => 'sk'],
            ['source_term' => 'posledny stvrt Mesiac', 'target_term' => 'posledná štvrť Mesiaca', 'language_from' => 'sk', 'language_to' => 'sk'],
            ['source_term' => 'posledny stvrt mesiac', 'target_term' => 'posledná štvrť Mesiaca', 'language_from' => 'sk', 'language_to' => 'sk'],
            ['source_term' => 'posledna stvrt', 'target_term' => 'posledná štvrť Mesiaca', 'language_from' => 'sk', 'language_to' => 'sk'],
            ['source_term' => 'nový Mesiac', 'target_term' => 'nov', 'language_from' => 'sk', 'language_to' => 'sk'],
            ['source_term' => 'Mesiac pri Zostupný uzol', 'target_term' => 'Mesiac pri zostupnom uzle', 'language_from' => 'sk', 'language_to' => 'sk'],
            ['source_term' => 'Mesiac pri vzostupný uzol', 'target_term' => 'Mesiac pri vzostupnom uzle', 'language_from' => 'sk', 'language_to' => 'sk'],
            ['source_term' => 'Mesiak pri vzostupný uzol', 'target_term' => 'Mesiac pri vzostupnom uzle', 'language_from' => 'sk', 'language_to' => 'sk'],
            ['source_term' => 'Mesiak pri životný uzol', 'target_term' => 'Mesiac pri zostupnom uzle', 'language_from' => 'sk', 'language_to' => 'sk'],
            ['source_term' => 'Mesiac pri zahraničný uzol', 'target_term' => 'Mesiac pri zostupnom uzle', 'language_from' => 'sk', 'language_to' => 'sk'],
            ['source_term' => 'Mesiac pri Apogee', 'target_term' => 'Mesiac v apogeu', 'language_from' => 'sk', 'language_to' => 'sk'],
            ['source_term' => 'Mesiac pri perigee', 'target_term' => 'Mesiac v perigeu', 'language_from' => 'sk', 'language_to' => 'sk'],
            ['source_term' => 'Annular zatmenie Slnka', 'target_term' => 'prstencové zatmenie Slnka', 'language_from' => 'sk', 'language_to' => 'sk'],
            ['source_term' => 'Posledná krajina Mesiaca', 'target_term' => 'posledná štvrť Mesiaca', 'language_from' => 'sk', 'language_to' => 'sk'],
            ['source_term' => 'položená štát Mesiaca', 'target_term' => 'posledná štvrť Mesiaca', 'language_from' => 'sk', 'language_to' => 'sk'],
            ['source_term' => 'Mesiak', 'target_term' => 'Mesiac', 'language_from' => 'sk', 'language_to' => 'sk'],
            ['source_term' => 'uplnok', 'target_term' => 'spln', 'language_from' => 'sk', 'language_to' => 'sk'],
        ];

        foreach ($rows as $row) {
            TranslationOverride::query()->updateOrCreate(
                [
                    'source_term' => $row['source_term'],
                    'language_from' => $row['language_from'] ?? 'en',
                    'language_to' => $row['language_to'] ?? 'sk',
                    'is_case_sensitive' => false,
                ],
                [
                    'target_term' => $row['target_term'],
                ]
            );
        }
    }
}
