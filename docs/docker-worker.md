# Docker backend + queue worker

Tento setup spusti frontend, Laravel API aj queue worker automaticky.

## Canonical dev URL (odporucane)

- Frontend: `http://127.0.0.1` (alebo `http://127.0.0.1:5174`)
- Backend API: `http://127.0.0.1:8001`

## Start

```bash
docker compose up -d --build
```

## Stop

```bash
docker compose down
```

## Logs

```bash
docker compose logs -f backend
docker compose logs -f queue-worker
docker compose logs -f frontend
```

## Poznamky

- Worker bezi ako samostatna sluzba `queue-worker` (`php artisan queue:work`).
- Backend aj worker citaju env z `backend/.env`.
- Databaza bezi ako kontajner `mysql` a backend sa pripaja cez `DB_HOST=mysql`.
- Moderation service bezi ako kontajner `moderation` na porte `8090`.
- Vyhni sa local `php artisan serve` a local `npm run dev`, inak sa miesaju runtime URL.
