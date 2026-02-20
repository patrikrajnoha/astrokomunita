# Local Development

## 1) Backend (Laravel API)

From `backend/`:

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve --host=127.0.0.1 --port=8000
```

Backend URL: `http://127.0.0.1:8000`  
Health check: `http://127.0.0.1:8000/api/health`

### Realtime (Laravel Reverb)

Run Reverb in a separate terminal from `backend/`:

```bash
php artisan reverb:start --host=0.0.0.0 --port=8080
```

Required `.env` keys:
- `BROADCAST_CONNECTION=reverb`
- `REVERB_HOST=127.0.0.1`
- `REVERB_PORT=8080`
- `REVERB_SCHEME=http`
- `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET`

## 2) Frontend (Vue + Vite)

From `frontend/`:

```bash
npm install
cp .env.example .env
npm run dev
```

Frontend URL: `http://127.0.0.1:5173`

The frontend API base URL is read from:
- `VITE_API_BASE_URL` (preferred)
- `VITE_API_URL` (legacy fallback)

Default expected backend for local dev is `http://127.0.0.1:8000`.

For realtime client config, frontend reads:
- `VITE_REVERB_APP_KEY`
- `VITE_REVERB_HOST`
- `VITE_REVERB_PORT`
- `VITE_REVERB_SCHEME`

Realtime checks:
- Browser DevTools -> Network -> `WS` should show an active Reverb socket connection.
- Backend route `POST /broadcasting/auth` should return `200` for your private channel.

Smoke scenario (local/dev):
1. Log in with user A in browser tab 1.
2. Call `POST /api/notifications/dev-test` as user A (or admin), payload:
   ```json
   {
     "type": "event_invite",
     "recipient_id": <USER_A_ID>,
     "event_id": 123,
     "event_title": "Meteor Shower Meetup"
   }
   ```
3. Verify without refresh:
   - unread badge increments in navbar/sidebar
   - notification appears in `/notifications` list when open

## Default Users

Regenerate default local/testing users with:

```bash
php artisan app:seed-default-users
```

Credentials:
- `admin` / `admin@admin.sk` / `admin`
- `astrobot` / `astrobot@astrobot.sk` / `astrobot`
- `patrik` / `patrik@patrik.sk` / `patrik`

## Generating Descriptions Safely

Use the robust batch command from `backend/`:

```bash
php artisan events:generate-descriptions --mode=ollama --concurrency=2 --resume --fallback=base
```

Useful options:
- `--resume` resume unfinished run (batch default when `--ids` is not used)
- `--no-resume` disable automatic resume
- `--from-id=123` start from a specific event id
- `--limit=50` process only a chunk
- `--force` regenerate even when description/short already exist
- `--fallback=base|skip`
- `--concurrency=N` expected worker concurrency for queue `descriptions` (default `2`)
- `--unsafe` allow `--concurrency` higher than safe cap (`3`)

Fallback behavior:
- `--fallback=base` if Ollama is unavailable, continue with base/template descriptions.
- `--fallback=skip` if Ollama fails for an event, skip that event and continue batch.

Windows worker command:

```bash
php artisan queue:work --queue=descriptions --sleep=1 --tries=1
```

Run this in separate terminals:
- concurrency `2` -> run 2 worker terminals (safe default)
- concurrency `3` -> run 3 worker terminals (max recommended)

Recommended for i5-9300H + 16GB RAM + GTX 1650:
- `--concurrency=2` (safe)
- `--concurrency=3` (max recommended)
- Not recommended: `>3` on 16GB RAM unless explicitly testing with `--unsafe`

Exit codes:
- `0` completed successfully
- `2` completed, but with failed events
- `1` fatal configuration/runtime error

## 3) Optional workers (recommended in dev)

From `backend/`:

```bash
php artisan queue:work
php artisan schedule:work
```

These are needed for async jobs and scheduled tasks.
