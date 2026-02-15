# Astropixels Integration Audit (Faza 0)

## Backend (current state)
- Existing ingestion flow already writes into `event_candidates` via `App\Services\EventImport\EventImportService` and is triggered by `events:import` / `events:import:tracked` commands (`backend/app/Console/Commands/ImportEventCandidates.php`, `backend/app/Console/Commands/ImportEventsCommand.php`).
- Candidate review/publish workflow already exists and must be reused (`backend/app/Http/Controllers/Api/Admin/EventCandidateReviewController.php`, `backend/app/Services/Events/EventCandidatePublisher.php`).
- Existing persistence:
  - `events` (`backend/database/migrations/2026_01_03_115133_create_events_table.php`)
  - `event_candidates` (`backend/database/migrations/2026_01_04_171754_create_event_candidates_table.php`)
  - `crawl_runs` (`backend/database/migrations/2026_01_24_162201_create_crawl_runs_table.php`)
- Existing event list API contract uses range filters (`from`, `to`) in `EventIndexRequest` + `EventController@index` (`backend/app/Http/Requests/EventIndexRequest.php`, `backend/app/Http/Controllers/Api/EventController.php`).
- Scheduler currently has placeholder URL and runs old command (`backend/routes/console.php`), so this must be replaced with dedicated crawler command.

## Frontend (current state)
- Public events screen has list/calendar toggle and list-side filters, but no year/month/week source-aware filtering (`frontend/src/views/EventsView.vue`).
- Calendar currently fetches one month by `from`/`to`, with local month navigation and no year-week wrapper contract (`frontend/src/views/CalendarView.vue`).
- Event service currently sends only legacy filters (`frontend/src/services/events.js`).
- Router already stores event view mode via query (`view=calendar`) and can be extended for year/month/week query sync (`frontend/src/router/index.js`).

## Reuse strategy
- Reuse existing candidate persistence and review/publish pipeline.
- Extend contracts backward-compatibly by adding year/month/week wrappers that resolve to date ranges server-side.
- Keep `source_name` behavior for compatibility, while introducing normalized `event_sources` + FK relations.
