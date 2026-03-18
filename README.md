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

### 3. Stiahni AI model (len raz)

```bash
docker compose exec ollama ollama pull mistral:latest
```

### 4. Otvor aplikáciu

| Služba       | URL                          |
|--------------|------------------------------|
| Aplikácia    | http://127.0.0.1             |
| API          | http://127.0.0.1:8001        |
| Emaily       | http://127.0.0.1:8025        |
| Databáza     | http://127.0.0.1:8086        |

> **Databáza (Adminer):** server `mysql`, user `astro`, heslo `astro`, databáza `astrokomunita`

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
