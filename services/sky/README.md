# Sky + Translation Microservice

FastAPI service used by Laravel for:
- `GET /sky-summary` (observing info)
- `POST /translate` (offline EN -> SK translation via Argos Translate)

## Run locally

```powershell
cd services/sky
python -m venv .venv
.\.venv\Scripts\Activate.ps1
pip install -r requirements.txt
```

Set internal token (same value as backend `INTERNAL_TOKEN` / `TRANSLATION_INTERNAL_TOKEN`):

```powershell
$env:INTERNAL_TOKEN="change-me"
```

Start service:

```powershell
cd app
uvicorn main:app --host 127.0.0.1 --port 8010 --reload
```

## Install Argos EN -> SK model

```powershell
python -m argostranslate.package --update-package-index
python -m argostranslate.package --install translate-en_sk
```

If package name differs in your index, list available packages:

```powershell
python -m argostranslate.package --available-packages
```

## Translation endpoints

- `POST /translate` (requires `X-Internal-Token`)
  - request: `{ "text": "...", "from": "en", "to": "sk", "domain": "astronomy" }`
  - response: `{ "translated": "...", "meta": { "engine": "argos", "from": "en", "to": "sk", "took_ms": 123 } }`
- `GET /health` -> `{ "ok": true|false, "version": "..." }`
- `GET /diagnostics` (requires `X-Internal-Token`)
  - includes installed languages and `has_en_sk_pair`

## Notes

- Long text is chunked automatically (> `TRANSLATION_CHUNK_MAX_CHARS`, default `4000`).
- Astronomy terminology post-processing is applied when `domain == "astronomy"`.
- Existing sky summary endpoint remains unchanged:
  - `GET /sky-summary?lat=48.1486&lon=17.1077&tz=Europe/Bratislava&date=2026-02-11`
