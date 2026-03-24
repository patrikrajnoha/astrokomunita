# Astrokomunita

Webová aplikácia pre fanúšikov astronómie – eventy, pozorovania, príspevky a realtime upozornenia.

## Rýchly štart

### Požiadavky

- [Docker](https://docs.docker.com/get-docker/) + Docker Compose

### 1. Priprav konfiguračné súbory

```bash
cp backend/.env.example backend/.env
cp frontend/.env.example frontend/.env
```

Vygeneruj APP_KEY pre backend:

```bash
docker compose run --rm backend php artisan key:generate
```

### 2. Spusti stack

```bash
docker compose up -d --build
```

Pri prvom štarte sa automaticky nainštalujú závislosti, spustia migrácie a naštartujú všetky služby.

### 3. (Volitelne) Stiahni AI model pre Ollama

```bash
docker compose exec ollama ollama pull mistral:latest
```

Tento krok je potrebny iba pre funkcie, ktore realne volaju Ollama (AI generovanie opisov eventov, AI navrhy tagov, AI post-edit/refinement). Zakladna aplikacia bezi aj bez spusteneho Ollama kontajnera.

### 4. Otvor aplikáciu

| Služba       | URL                          |
|--------------|------------------------------|
| Aplikácia    | http://127.0.0.1:5174        |
| API          | http://127.0.0.1:8001        |
| Emaily       | http://127.0.0.1:8025        |
| Databáza     | http://127.0.0.1:8086        |

> **Databáza (Adminer):** server `your_db_host`, user `your_db_user`, heslo `your_db_password`, databáza `your_db_name`

### Zastavenie

```bash
docker compose down
```

## Vývoj bez Dockeru

### Backend (Laravel)

```bash
cd backend
composer install
php artisan serve
```

### Frontend (Vue + Vite)

```bash
cd frontend
npm install
npm run dev
```

### Testy

```bash
# Backend
cd backend && php artisan test

# Frontend
cd frontend && npx vitest run
```

## Viac informácií

Podrobnejšia dokumentácia je v [docs/dokumentacia.md](docs/dokumentacia.md).

## Production deploy (Docker)

1. Priprav konfiguraciu:
```bash
cp .env.prod.example .env
cp backend/.env.production.example backend/.env
```

2. Vypln produkcne domeny, hesla, tokeny a image piny v `.env` a `backend/.env`.

3. Spusti stack:
```bash
docker compose -f docker-compose.prod.yml pull
docker compose -f docker-compose.prod.yml up -d --no-build
```

4. Over health:
```bash
curl -f https://api.tvoja-domena.sk/api/health
```

## Production DB backup / restore

Jednorazovy backup:
```bash
./scripts/prod-db-backup.sh
```

Restore (destruktivna operacia):
```bash
CONFIRM_RESTORE=YES ./scripts/prod-db-restore.sh backups/mysql_YYYYMMDDTHHMMSSZ.sql.gz
```
