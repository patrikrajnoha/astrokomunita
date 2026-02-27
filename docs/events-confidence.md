# Public Confidence Badge (Events)

`public_confidence` je verejne API pole na evente, ktore vysvetluje heuristicku doveryhodnost udalosti.

## API shape

Kazdy event v list/detail API obsahuje:

- `public_confidence.level`: `verified` | `partial` | `low` | `unknown`
- `public_confidence.label`: user-facing text
- `public_confidence.score`: 0-100 alebo `null`
- `public_confidence.sources_count`: pocet zdrojov alebo `null`
- `public_confidence.reason`: kratke vysvetlenie pre tooltip

## Pravidla (MVP)

Thresholdy su konfigurovatelne v `config/events.php` (`events.public_confidence.*`):

- `verified`: `score >= verified_score` a `sources_count >= verified_min_sources`
- `partial`: `score >= partial_score` a `sources_count >= partial_min_sources`
- `low`: vsetko ostatne
- `unknown`: chybajuce score

Default hodnoty:

- `verified_score = 80`
- `partial_score = 60`
- `verified_min_sources = 2`
- `partial_min_sources = 1`

Poznamka: ide o transparentnu heuristiku, nie formalnu verifikaciu faktov.
