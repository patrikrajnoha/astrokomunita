<?php

return [
    'prompts' => [
        'legacy' => [
            'rules' => [
                // Hallucination prevention — most critical
                'Pouzi VYLUCNE fakty z JSON vstupu. Nepridavaj ziadne informacie ktore tam nie su.',
                'Nikdy nepridavaj cas viditelnosti, hodinu, lokalitu, magnitude ani meteosituaciu, ak nie su explicitne v JSON vstupe.',
                'Nikdy netvrd ze jav je viditelny "z celej krajiny", "z celého Slovenska" ani "vo vsetkych oblastiach".',
                'Nikdy neuvadzaj vzacnost ani historickost javu ("raz za X rokov", "historicky", "mimoriadne vzacny") bez priameho zdroja vo vstupe.',
                'Nikdy nespajaj objekty podla podobnosti nazvu — Ursidy nie su planeta Uranus, Leonidy nie su hviezda Lev.',
                // Structure
                'Description: 2-3 plne vety, max 500 znakov.',
                'Veta 1: co za astronomicky jav to je.',
                'Veta 2: ako ho pozorovat — iba na zaklade udajov zo vstupu; ak chybaju, formuluj vseobecne.',
                'Veta 3 (volitelna): kratka zaujimavost bez odhadov.',
                'Pozorovacie rady formuluj vseobecne: "Jav mozno pozorovat volnym okom" — nie "budete vidiet z vasho mesta".',
                // Language
                'Plynula prirodzena slovencina so spravnou diakritikou, bez doslovneho prekladu z anglictiny.',
                'Bez markdownu.',
            ],
        ],
        'humanized' => [
            'rules' => [
                'Vrat STRICT JSON objekt bez markdownu a bez dodatocneho textu.',
                'JSON musi obsahovat presne kluce: "description", "short", "why_interesting", "how_to_observe".',
                'Kazda hodnota musi byt string.',
                'Limity dlzky: short max 180, description max 500, why_interesting max 200, how_to_observe max 250 znakov.',
                'NIKDY nemen cisla, datumy, casy ani nazvy objektov z factual packu.',
                'Ak informacia nie je vo factual packu, nepridavaj ju ako fakt.',
                'Nikdy nepridavaj cas, lokalitu ani magnitude, ak nie su vo factual packu.',
                'Nikdy netvrd ze jav je viditelny "z celej krajiny" alebo "vo vsetkych oblastiach".',
                'Jazyk musi byt prirodzena plynula slovencina (bez doslovneho prekladu a umelych slov).',
                'Pozorovacie rady formuluj vseobecne bez konkretnych neoverenych tvrdeni.',
                'Ak je informacia nejasna alebo chyba, pouzi neutralne formulacie a neuvadzaj odhady.',
                'Nikdy nespajaj objekty iba podla podobnosti nazvu (napr. Ursids nie je planeta Uranus).',
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

