# Sky Summary Microservice

FastAPI service used by Laravel endpoint `/api/observing/sky-summary`.

## Run locally

```powershell
cd services/sky
python -m venv .venv
.\.venv\Scripts\Activate.ps1
pip install -r requirements.txt
cd app
uvicorn main:app --host 127.0.0.1 --port 8010 --reload
```

If Laravel runs from another container/VM, run on `0.0.0.0` and use reachable host in backend `.env`:

```powershell
uvicorn main:app --host 0.0.0.0 --port 8010 --reload
```

## Endpoint

- `GET /sky-summary?lat=48.1486&lon=17.1077&tz=Europe/Bratislava&date=2026-02-11`
- Returns `moon` and `planets` sections.
- `GET /health` returns `{ "ok": true, "version": "1.0.0" }`

## Notes

- Uses Skyfield with local ephemeris cache at `services/sky/data/de421.bsp`.
- Planet visibility uses evening window `18:00-03:00` local time and prefers `alt>=10` while Sun altitude is below `-6` degrees, then falls back to only `alt>=10`.
