# Translation pipeline note (EN -> SK)

## Source and processing
- English texts are ingested from AstroBot RSS (`rss_items`) and crawler candidates (`event_candidates`).
- Translation runs asynchronously through queued jobs (`TranslateRssItemJob`, `TranslateEventCandidateJob`).
- Provider chain is open-source only:
  - primary: self-hosted LibreTranslate
  - fallback: local Argos-based microservice (`services/sky`)

## Terminology and consistency
- Domain overrides are stored in `translation_overrides` and applied before and after provider translation.
- This enforces astronomy terminology consistency (e.g., conjunction, opposition, equinox).

## Idempotence and deduplication
- Translation cache key is `sha256(normalized_text|from|to)`.
- Cached results are stored in `translation_cache_entries` and reused to avoid duplicate external calls.
- Repeated job runs are idempotent via status checks (`pending|done|failed`) and unique jobs.

## Observability and safety
- Each translation attempt writes `translation_logs` with provider, status, error code, and duration.
- Admin health endpoint: `GET /api/admin/translation-health`.
- Failures mark record status as `failed` and keep the ingest/review flow functional.

## Review workflow alignment
- Crawled events remain in the candidate review process.
- When translation exists, published event content prefers translated fields with fallback to originals.
