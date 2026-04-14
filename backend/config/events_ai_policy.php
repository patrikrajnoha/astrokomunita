<?php

return [
    'prompts' => [
        'legacy' => [
            'rules' => [
                // Hallucination prevention — most critical
                'Použi VÝLUČNE fakty z JSON vstupu. Nepridávaj žiadne informácie, ktoré tam nie sú.',
                'Nikdy nepridávaj čas viditeľnosti, hodinu, lokalitu, magnitúdu ani meteosituáciu, ak nie sú explicitne v JSON vstupe.',
                'Nikdy netvrď, že jav je viditeľný "z celej krajiny", "z celého Slovenska" ani "vo všetkých oblastiach".',
                'Nikdy neuvádzaj vzácnosť ani historickosť javu ("raz za X rokov", "historický", "mimoriadne vzácny") bez priameho zdroja vo vstupe.',
                'Nikdy nespájaj objekty podľa podobnosti názvu — Ursidy nie sú planéta Uránus, Leonidy nie sú hviezda Lev.',
                // Structure
                'Description: 2-3 plne vety, max 500 znakov.',
                'Veta 1: čo za astronomický jav to je.',
                'Veta 2: ako ho pozorovať — iba na základe údajov zo vstupu; ak chýbajú, formuluj všeobecne.',
                'Veta 3 (voliteľná): krátka zaujímavosť bez odhadov.',
                'Pozorovacie rady formuluj všeobecne: "Jav možno pozorovať voľným okom" — nie "budete vidieť z vášho mesta".',
                // Language
                'Plynulá prirodzená slovenčina so správnou diakritikou, bez doslovného prekladu z angličtiny.',
                'Bez markdownu.',
            ],
        ],
        'humanized' => [
            'rules' => [
                'Vráť STRICT JSON objekt bez markdownu a bez dodatočného textu.',
                'JSON musí obsahovať presne kľúče: "description", "short", "why_interesting", "how_to_observe".',
                'Každá hodnota musí byť string.',
                'Limity dĺžky: short max 180, description max 500, why_interesting max 200, how_to_observe max 250 znakov.',
                'NIKDY nemeň čísla, dátumy, časy ani názvy objektov z factual packu.',
                'Ak informácia nie je vo factual packu, nepridávaj ju ako fakt.',
                'Nikdy nepridávaj čas, lokalitu ani magnitúdu, ak nie sú vo factual packu.',
                'Nikdy netvrď, že jav je viditeľný "z celej krajiny" alebo "vo všetkých oblastiach".',
                'Jazyk musí byť prirodzená plynulá slovenčina (bez doslovného prekladu a umelých slov).',
                'Pozorovacie rady formuluj všeobecne bez konkrétnych neoverených tvrdení.',
                'Ak je informácia nejasná alebo chýba, použi neutrálne formulácie a neuvádzaj odhady.',
                'Nikdy nespájaj objekty iba podľa podobnosti názvu (napr. Ursids nie je planéta Uránus).',
            ],
        ],
    ],

    'safety' => [
        'numeric_token_guard_enabled' => true,
        'celestial_term_guard_enabled' => true,
        'artifact_guard_enabled' => true,

        'celestial_terms' => [
            'slnko',
            'mesiac',
            'zem',
            'merkur',
            'venus',
            'mars',
            'jupiter',
            'saturn',
            'uran',
            'neptun',
            'pluto',
            'regulus',
            'spica',
            'antares',
            'pollux',
            'pleiades',
            'plejady',
        ],

        'forbidden_substrings' => [
            'conductov',
            'kontakt s ludmi na mesiac',
            'kontaktu s ludmi na mesiac',
            'z celého slovenska',
            'z celej krajiny',
            'vo všetkých oblastiach',
            'na celom slovensku',
            'prvýkrát za',
            'magnitude',
        ],
        'forbidden_regex' => [
            // Hallucinated rise/set times: "vychádza o 22", "zapadá o 5"
            '/\\b(vychádza|vychádza sa|zapad[aá]) o \\d/iu',
            // Hallucinated clock times: "o 22:30", "o 22h", "o 4.15"
            '/\\bo \\d{1,2}[:h.]\\d{0,2}\\b/iu',
            // Magnitude values: "jasnosť 4,5 mag", "3.2 magnitúd"
            '/\\b\\d+[.,]\\d*\\s*mag(nitúd)?\\b/iu',
            // Rarity claims: "prvýkrát za 50 rokov"
            '/\\bprvýkrát za \\d+/iu',
            // Superlative hallucinations
            '/\\bhistorická udalosť\\b/iu',
            '/\\bmimoriadne vzácn/iu',
            '/\\bniečo výnimočn/iu',
        ],
    ],
];
