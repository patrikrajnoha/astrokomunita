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
php artisan events:generate-descriptions --mode=ollama --fallback=skip
```

Useful options:
- `--resume` resume unfinished run (batch default when `--ids` is not used)
- `--no-resume` disable automatic resume
- `--from-id=123` start from a specific event id
- `--limit=50` process only a chunk
- `--force` regenerate even when description/short already exist
- `--fallback=base|skip`

Fallback behavior:
- `--fallback=base` if Ollama is unavailable, continue with base/template descriptions.
- `--fallback=skip` if Ollama fails for an event, skip that event and continue batch.

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
