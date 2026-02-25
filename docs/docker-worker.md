# Docker backend + queue worker

Tento setup spusti Laravel API aj queue worker automaticky.

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
```

## Poznamky

- Worker bezi ako samostatna sluzba `queue-worker` (`php artisan queue:work`).
- Backend aj worker citaju env z `backend/.env`.
- V compose je pre kontajnery nastavene `DB_HOST=host.docker.internal`, aby sa vedeli pripojit na DB beziciu na hoste.
- Moderation service bezi ako kontajner `moderation` na porte `8090`.
