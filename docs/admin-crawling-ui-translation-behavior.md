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