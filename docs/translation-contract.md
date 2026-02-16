# Translation Contract

## Target language invariant
- All automatic translations in this project target Slovak (`sk`).
- This applies to:
  - Event candidates (`translated_title`, `translated_description`)
  - RSS items (`translated_title`, `translated_summary`)

## Source text persistence
- Jobs always persist original source text in:
  - `original_title`
  - `original_description` / `original_summary`

## Failure fallback
- If translation provider fails, jobs keep pipeline continuity by persisting original text into translated fields.
- Failure is still explicit:
  - `translation_status = failed`
  - `translation_error` contains provider error code
  - warning log entry is emitted

## Queue behavior
- Translation is intended to run async via queue jobs.
- Event import may skip dispatch when queue driver is `sync` and `TRANSLATION_ALLOW_SYNC_QUEUE=false`.
