# Astropixels Crawler Note (Source, Timezone, Idempotency)

## Zdroj
- Primarny zdroj udalosti: Astropixels Sky Event Almanac (CET pages 2021-2030):
  - `https://astropixels.com/almanac/almanac21/almanac{YEAR}cet.html`
- Integracia nepouziva ziadne platene API.

## Casove pasmo
- Vstupne datumy/casy z Astropixels sa interpretuju ako `Europe/Bratislava` (CET/CEST).
- Pred ulozenim do databazy sa cas konvertuje do UTC.
- API vracia casy v ISO 8601 formate s UTC casovou zonou.

## Idempotencia a deduplikacia
- Kazdy kandidat ma stabilny identifikator:
  - preferuje sa `href` (ak je dostupny v riadku zdroja),
  - fallback je hash z `normalized_title + starts_at_utc + source_url + year + row_signature`.
- Kandidati ukladaju `external_id` aj `stable_key`.
- Unikatne indexy na `(event_source_id, external_id)` a `(event_source_id, stable_key)` podporuju deterministicke opakovane crawly.
- Import je navrhnuty ako idempotentny upsert:
  - `created` pri novom zazname,
  - `updated` pri zmene pending kandidata,
  - `skipped_duplicates` pri nezmenenom zazname.

## Review workflow
- Crawler/import zapisuje iba do `event_candidates`.
- Publikacia do `events` prechadza existujucim admin review workflow (approve/reject).
- Schvalene kandidaty ostavaju auditovatelne cez vazbu na `crawl_runs` a zdrojove metadata.
