# Admin Crawling UI + Translation Behavior

## Crawling admin flow

- `/admin/event-sources` is a compact 3-step panel:
  - Run panel (year + `Run selected`)
  - Sources table (enable toggle, per-row run, last run status, counters)
  - Recent runs table (`View candidates`, `Details`)
- Unsupported sources are visible but cannot be run manually (`Deferred in MVP`).

## Run detail and candidate handoff

- `/admin/crawl-runs/:id` shows run metadata, counters, optional error section, and CTA to open candidates.
- Opening candidates from run sends `run_id`, `source_key`, and `year` query params.
- Candidates list displays a run filter chip and can clear it inline.

## Translation and SK fallback

- Candidate import now dispatches translation job even when queue driver is `sync`.
- Candidate translation keeps fail-open behavior:
  - successful provider => translated fields are stored
  - provider failure => candidate still gets deterministic SK template description/short
- Admin candidate list and detail prefer translated fields (`translated_title`, `translated_description`) before original EN fields.
- Candidate detail shows translation status + last error and provides `Retranslate` action.

## Retranslate endpoint

- `POST /api/admin/event-candidates/{id}/retranslate`
- Sets candidate translation status to `pending`, clears `translation_error`, and queues translation job with `force=true`.

## Run-based filtering (backend)

- `GET /api/admin/event-candidates?run_id=...`
- If direct `crawl_run_id` linkage is not available, backend filters by:
  - run source (`event_source_id` / `source_name`)
  - candidate `created_at` in run time window (`started_at..finished_at`, with small tolerance)

## Bot run hardening (follow-up)

- Canonical translation env naming:
  - `TRANSLATION_PROVIDER=ollama|libretranslate`
  - `TRANSLATION_TIMEOUT_SEC=12`
  - `TRANSLATION_MAX_RETRIES=1`
  - `TRANSLATION_FALLBACK_PROVIDER=libretranslate|none`
  - `LIBRETRANSLATE_BASE_URL=http://127.0.0.1:5000`
  - `LIBRETRANSLATE_API_KEY=` (optional)
  - `OLLAMA_BASE_URL=http://127.0.0.1:11434`
  - `OLLAMA_MODEL=mistral`
  - `OLLAMA_NUM_PREDICT=280`
- Backward-compatible aliases are still accepted temporarily:
  - `BOT_TRANSLATION_LIBRETRANSLATE_URL`, `TRANSLATION_BASE_URL`
  - `BOT_TRANSLATION_LIBRETRANSLATE_API_KEY`
  - `BOT_TRANSLATION_PRIMARY`, `BOT_TRANSLATION_FALLBACK`
  - `TRANSLATION_TIMEOUT_SECONDS`, `BOT_TRANSLATION_LIBRETRANSLATE_TIMEOUT_SECONDS`
- Debug mode logs alias usage (alias names only, no secret values).

## LibreTranslate local run

```bash
docker run --rm -p 5000:5000 libretranslate/libretranslate:latest
```

- Then set:
  - `TRANSLATION_PROVIDER=libretranslate`
  - `LIBRETRANSLATE_BASE_URL=http://127.0.0.1:5000`

## Bot translation diagnostics

- Admin endpoint: `GET /api/admin/bots/translation/health`
  - returns provider, base URL, timeout, `degraded`, and `result.ok/error_type`
  - `degraded=true` means primary failed but fallback provider succeeded
  - API keys are never returned
- Admin endpoint: `POST /api/admin/bots/translation/simulate-outage`
  - payload: `{ "provider": "none|ollama|libretranslate" }`
  - persists `translation.simulate_outage_provider` (admin-only, audited log with old/new value)
- Failure reasons are normalized and shared across FE/BE (single source of truth).

## Debug checklist

- FE timeout verified for bot run endpoints (`60s` only for run/quick-run).
- Stale run recovery verified (`stale_run_recovered`, `recovered_by_run_id`).
- Translation fail-open verified (run finishes; publish uses original text on translation failure).
- Backend CI split verified (`Unit`, `Feature`, `Bots/Translation sanity` jobs).
