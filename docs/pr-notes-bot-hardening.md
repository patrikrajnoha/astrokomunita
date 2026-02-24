# PR Notes: Bot Run Follow-up Hardening

## What changed

- Unified translation env naming with canonical variables and temporary alias fallback.
- Unified bot run failure reasons with backend enum and frontend constants map.
- Added typed timeout/unavailable error mapping and stronger timeout/connect-timeout handling.
- Added stale run recovery checks/tests for older vs younger unfinished runs.
- Added admin diagnostics endpoint for bot translation provider health.
- Added admin outage simulation toggle (`none|ollama|libretranslate`) persisted in settings (`translation.simulate_outage_provider`).
- Added lock-release resilience test for throwable path.

## Canonical env setup

- `TRANSLATION_PROVIDER=ollama|libretranslate`
- `TRANSLATION_TIMEOUT_SEC=12`
- `TRANSLATION_MAX_RETRIES=1`
- `TRANSLATION_FALLBACK_PROVIDER=libretranslate|none`
- `LIBRETRANSLATE_BASE_URL=http://127.0.0.1:5000`
- `LIBRETRANSLATE_API_KEY=` (optional)
- `OLLAMA_BASE_URL=http://127.0.0.1:11434`
- `OLLAMA_MODEL=mistral`
- `OLLAMA_NUM_PREDICT=280`

## Compatibility

- Legacy env aliases are still supported temporarily:
  - `BOT_TRANSLATION_LIBRETRANSLATE_URL`, `TRANSLATION_BASE_URL`
  - `BOT_TRANSLATION_LIBRETRANSLATE_API_KEY`
  - `BOT_TRANSLATION_PRIMARY`, `BOT_TRANSLATION_FALLBACK`
  - `TRANSLATION_TIMEOUT_SECONDS`, `BOT_TRANSLATION_LIBRETRANSLATE_TIMEOUT_SECONDS`

## Verification checklist

- [x] FE timeout for bot run endpoints verified.
- [x] Stale run recovery behavior verified.
- [x] Translation fail-open behavior verified.
- [x] CI backend jobs split (`Unit`, `Feature`, `Bots/Translation sanity`).
- [x] Translation health `degraded` behavior verified.
