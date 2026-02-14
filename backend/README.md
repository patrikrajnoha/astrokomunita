<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## AstroKomunita Project

## Media storage (uploads)

- Uploads use Laravel Storage API only (no direct writes to `/public`).
- Database stores only file paths (`avatar_path`, `cover_path`, `attachment_path`, `cover_image_path`), not blobs.
- Frontend consumes URLs returned by API (`avatar_url`, `cover_url`, `attachment_url`, `cover_image_url`).

### Setup

```powershell
php artisan storage:link
```

### Environment

```env
FILES_DISK=public
FILES_CLOUD_DISK=s3
```

- `FILES_DISK=public` (dev default): files are saved under `storage/app/public` and served via `/storage` symlink.
- For cloud-ready deployment, switch `FILES_DISK` to a cloud disk (for example `s3`) and configure that disk.

### Folder layout

- avatars: `avatars/{userId}/...`
- covers: `covers/{userId}/...`
- post attachments: `posts/{postId}/...`
- blog covers: `blog-covers/{userId}/...`

## Personalizovany feed udalosti

### Co sa uklada
- Pouzivatelske preferencie su v tabulke `user_preferences`:
  - `event_types` (JSON array typov udalosti)
  - `region` (`sk|eu|global`)
  - `updated_at` (automaticke casove razitko)
- Udalosti maju doplnene `events.region_scope` (`sk|eu|global`) pre region filter.

### Ako funguje `scopeForUser`
- Scope `Event::forUser(?User $user)` aplikuje personalizaciu len ak je pouzivatel prihlaseny a ma ulozene preferencie.
- `event_types` filtruje udalosti cez `whereIn(type, ...)`.
- `region` filtruje cez `region_scope`:
  - `sk`: povolene `sk`, `eu`, `global`
  - `eu`: povolene `eu`, `global`
  - `global`: bez region obmedzenia
- Pri prazdnom `event_types` sa to interpretuje ako \"vsetky typy\".

### UX personalizacia na `/events`
- Feed ma segment `Vsetko` vs `Pre mna`.
- Neprihlaseny pouzivatel vidi `Pre mna` ako disabled + CTA na prihlasenie.
- Prihlaseny pouzivatel ma panel `Moje preferencie` (multi-select typov + region + ulozenie).
- Pri feede `Pre mna` su pokryte empty states:
  - bez ulozenych preferencii -> CTA na nastavenie
  - preferencie existuju, ale bez vysledkov -> CTA na upravu filtrov

## Notifications System (Bachelor Thesis)

### ERD (text)
- notifications: id, user_id (FK users.id), type, data (json), read_at, created_at, updated_at
  - indexes: (user_id, read_at), (user_id, created_at), (user_id, type)
- notification_events: id, hash (unique), notification_id (FK notifications.id), created_at, updated_at

### Use-case scenáre
1. Post like
   - používateľ B lajkuje post používateľa A → vznikne notifikácia typu `post_liked`
   - deduplikácia: pre (recipient, actor, post) max 1 unread notifikácia, pri opakovaní sa len bumpne čas
2. Event reminder
   - scheduler nájde eventy štartujúce v okne T-60
   - vytvorí `event_reminder` notifikáciu pre subscriberov alebo globálne pre všetkých aktívnych userov
   - idempotencia cez `notification_events.hash`

### Scheduler (lokálne)
```powershell
php artisan schedule:work
```

### API endpoints
- GET /api/notifications (page, per_page)
- GET /api/notifications/unread-count
- POST /api/notifications/{id}/read
- POST /api/notifications/read-all
- Search endpoints (`/api/search/users`, `/api/search/posts`, `/api/search/suggest`) are rate-limited to `60 requests/minute/IP`.

### Observing conditions sidebar API
- Endpoint: `GET /api/observe/summary?lat=48.1486&lon=17.1077&date=2026-02-10&tz=Europe/Bratislava`
- Sky summary endpoint: `GET /api/observing/sky-summary?lat=48.1486&lon=17.1077&date=2026-02-10&tz=Europe/Bratislava`
- Local diagnostics endpoint: `GET /api/observe/diagnostics?lat=48.1486&lon=17.1077&date=2026-02-10&tz=Europe/Bratislava`
  - dostupny iba v `APP_ENV=local`, inak vracia 404
- Aggreguje:
  - USNO: sunrise/sunset, civil twilight, moon phase + illumination
  - Open-Meteo: humidity (current + evening)
  - OpenAQ: PM2.5/PM10 (najblizsia stanica v radiuse)
- Pri zlyhani providera endpoint stale vracia `200`, ale prislusna sekcia ma `status: "unavailable"`.
- V `APP_ENV=local` endpoint vracia aj necitlive `debug.reason` pri sekcii, ktora zlyha.
- Cache:
  - plny vysledok: `OBSERVING_CACHE_TTL_MINUTES` (default 15)
  - partial vysledok: `OBSERVING_CACHE_PARTIAL_TTL_MINUTES` (default 5)
  - ked su vsetky sekcie unavailable: `OBSERVING_CACHE_ALL_UNAVAILABLE_TTL_SECONDS` (default 90s)
  - sky summary payload: `OBSERVING_SKY_CACHE_TTL_MINUTES` (default 60)

Nastavenie `.env`:

```env
OPENAQ_API_KEY=your_openaq_key
OPENAQ_BASE_URL=https://api.openaq.org/v3
OBSERVING_DEFAULT_TZ=Europe/Bratislava
OBSERVING_CA_BUNDLE_PATH=C:\absolute\path\to\backend\storage\certs\cacert.pem
OBSERVING_SKY_MICROSERVICE_BASE=http://127.0.0.1:8010
OBSERVING_SKY_ENDPOINT_PATH=/sky-summary
OBSERVING_SKY_HEALTH_PATH=/health
OBSERVING_SKY_CACHE_TTL_MINUTES=60
```

Sky microservice quick checks:

```powershell
curl "http://127.0.0.1:8010/health"
curl "http://127.0.0.1:8010/sky-summary?lat=48.1486&lon=17.1077&tz=Europe/Bratislava&date=2026-02-11"
```

Po zmene `.env`:

```powershell
php artisan config:clear
```

### Windows/XAMPP SSL fix (cURL error 60)
Ak USNO/Open-Meteo padaju s `cURL error 60`, PHP nema CA bundle.

1. V repozitari je local CA bundle:
   - `backend/storage/certs/cacert.pem`
2. Najdi Apache `php.ini` (XAMPP PHP) a nastav:

```ini
curl.cainfo="ABSOLUTE_PATH_TO_PROJECT\backend\storage\certs\cacert.pem"
openssl.cafile="ABSOLUTE_PATH_TO_PROJECT\backend\storage\certs\cacert.pem"
```

3. Restartuj Apache.
4. Ak volas Artisan cez ine PHP binarky, over ze CLI pouziva rovnake `php.ini`.

Smoke test (PowerShell):

```powershell
irm "http://127.0.0.1:8000/api/observe/diagnostics?lat=48.1486&lon=17.1077&date=2026-02-10&tz=Europe/Bratislava" | ConvertTo-Json -Depth 10
```

### Auth registration rules
- POST `/api/auth/register`
  - required fields: `name`, `email`, `password`, `password_confirmation`, `username`, `date_of_birth`
  - `username` is normalized to lower-case and must match: 3-20 chars, starts with letter, only `[a-z0-9_]`, no `__`
  - blocked usernames are configured in `config/auth.php` under `auth.username.reserved` and `auth.username.blocked_words`
  - minimum age is 13 (`date_of_birth` must be before or equal to `today - 13 years`)
- GET `/api/auth/username-available?username=...`
  - public endpoint with throttle `30/min/IP`
  - response:
    - `{ "username": "abc", "normalized": "abc", "available": true, "reason": "ok" }`
    - `reason` can be: `ok`, `taken`, `reserved`, `invalid`

### API Testing on Windows (PowerShell)

**Important:** This project runs on Windows + PowerShell. For API testing, use PowerShell commands instead of Unix-style `curl | jq`.

```powershell
# Public endpoint
irm http://localhost:8000/api/sidebar-sections | ConvertTo-Json -Depth 10

# Admin endpoint (requires auth)
irm http://localhost:8000/api/admin/sidebar-sections -Headers @{"Authorization"="Bearer YOUR_TOKEN"} | ConvertTo-Json -Depth 10
```

📖 **Full documentation:** See `API_TESTING.md` for complete testing guide.

> **Note:** In PowerShell, `curl` is an alias for `Invoke-WebRequest`, so we use `Invoke-RestMethod` (irm) for API calls.

### Scheduler (local dev)

For local development on Windows, run the scheduler worker in a separate terminal so AstroBot cleanup runs automatically:

```powershell
php artisan schedule:work
```

To test the purge command manually:

```powershell
php artisan astrobot:purge-old-posts --dry-run
```

## Moderation (local, free)

This project supports fully self-hosted moderation with Laravel + FastAPI + HuggingFace models.

### Architecture
- Laravel publishes posts immediately (`201`) with `moderation_status=pending`.
- `ModeratePostJob` runs asynchronously and updates moderation status.
- Image attachments stay blurred while pending to reduce NSFW flashes.
- Admin review queue is available at `/admin/moderation`.

### Backend env config
Add these values to `.env`:

```env
MODERATION_ENABLED=true
MODERATION_BASE_URL=http://127.0.0.1:8090
MODERATION_INTERNAL_TOKEN=change-me
MODERATION_FALLBACK_POLICY=pending_blur_retry
MODERATION_TEXT_FLAG_THRESHOLD=0.70
MODERATION_TEXT_BLOCK_THRESHOLD=0.90
MODERATION_IMAGE_FLAG_THRESHOLD=0.60
MODERATION_IMAGE_BLOCK_THRESHOLD=0.85
```

### Local FastAPI service
Use `../moderation-service/README.md` for setup. For Windows/XAMPP Laravel dev, running the microservice on `http://127.0.0.1:8090` is enough.

### Queue worker
Moderation is async. Keep queue worker running:

```powershell
php artisan queue:work
```

Notes:
- In local env, post creation logs a warning if moderation uses async queue and worker is probably missing.
- Moderation jobs are dispatched `afterCommit`, so workers do not race uncommitted posts.
- For quick manual debug, run:

```powershell
php artisan moderation:run 123
```

## AstroBot RSS automation

### What runs automatically
- Scheduler runs `astrobot:sync-rss` every hour.
- Import is idempotent via `stable_key` (GUID preferred, fallback hash(link + published_at)).
- Auto-publish is ON by default for safe items.
- Risky items are routed to `needs_review` inbox.
- Cleanup removes items missing from current RSS and items older than `ASTROBOT_RSS_MAX_AGE_DAYS`.

### Configuration
Set these in `.env` if needed:

```env
ASTROBOT_RSS_URL=https://www.nasa.gov/news-release/feed/
ASTROBOT_RSS_TIMEOUT_SECONDS=10
ASTROBOT_RSS_RETRY_TIMES=2
ASTROBOT_RSS_RETRY_SLEEP_MS=250
ASTROBOT_MAX_ITEMS_PER_SYNC=80
ASTROBOT_RSS_MAX_PAYLOAD_KB=1024
ASTROBOT_RSS_MAX_AGE_DAYS=30
ASTROBOT_AUTO_PUBLISH_ENABLED=true
ASTROBOT_DOMAIN_WHITELIST=nasa.gov,www.nasa.gov
ASTROBOT_RISK_KEYWORDS=!!!,crypto,free,win
ASTROBOT_SSL_VERIFY=true
ASTROBOT_SSL_CA_BUNDLE=
TRANSLATION_SERVICE_URL=http://127.0.0.1:8010
TRANSLATION_SERVICE_TRANSLATE_PATH=/translate
TRANSLATION_SERVICE_DIAGNOSTICS_PATH=/diagnostics
TRANSLATION_TIMEOUT_SECONDS=12
TRANSLATION_CONNECT_TIMEOUT_SECONDS=3
TRANSLATION_RETRIES=2
TRANSLATION_RETRY_SLEEP_MS=250
INTERNAL_TOKEN=change-me
TRANSLATION_INTERNAL_TOKEN=change-me
```

### NASA RSS SSL setup (Windows/XAMPP + production)

AstroBot NASA sync keeps SSL verification enabled. If PHP cannot validate certificates (`cURL error 60`), provide a CA bundle.

1. Download `cacert.pem` (curl CA bundle) and place it in one of:
   - `backend/storage/cacert.pem` (preferred)
   - `backend/cacert.pem`
2. Set `.env`:

```env
ASTROBOT_SSL_VERIFY=true
ASTROBOT_SSL_CA_BUNDLE=C:\absolute\path\to\backend\storage\cacert.pem
```

3. Reload config:

```powershell
php artisan optimize:clear
```

4. Verify fetch works:

```powershell
php artisan astrobot:sync-rss
```

If sync still fails, check latest run in admin (`/api/admin/astrobot/nasa/status`) and `storage/logs/laravel.log` for the full `error_message`.

Manual emergency sync command:

```powershell
php artisan astrobot:sync-rss
```

Manual admin API sync endpoint (admin only, rate-limited):
- `POST /api/admin/astrobot/sync`
- `GET /api/admin/astrobot/items?status=needs_review`
- `POST /api/admin/astrobot/items/{id}/publish`
- `POST /api/admin/astrobot/items/{id}/reject`
- `POST /api/admin/astrobot/rss-items/{id}/retranslate` (force retranslate)
- `POST /api/admin/astrobot/rss-items/retranslate-pending` (batch max 100)

### Translation flow (EN -> SK)
- New/updated RSS items are queued for translation (`TranslateRssItemJob`) after DB commit.
- `rss_items` stores:
  - `original_title`, `original_summary`
  - `translated_title`, `translated_summary`
  - `translation_status` (`pending|done|failed`)
  - `translation_error`, `translated_at`
- Auto-publish waits for `translation_status=done`, so published AstroBot content uses Slovak translation.
- Keep queue worker running:

```powershell
php artisan queue:work
```

### Development (Windows/XAMPP)
Run scheduler worker in a separate terminal:

```powershell
php artisan schedule:work
```

### Production (Linux cron)
Use one cron entry:

```cron
* * * * * cd /path && php artisan schedule:run >> /dev/null 2>&1
```

### Troubleshooting
- If scheduled tasks do not run, verify server timezone and cron/service permissions.
- If `onOneServer()` lock behaves unexpectedly, check cache driver setup (`redis`/shared cache recommended for multi-server).
- If RSS sync fails intermittently, inspect `storage/logs/laravel.log` for `AstroBot RSS sync` warnings.
- If later moved to queued jobs, ensure queue worker is running (`php artisan queue:work`) and queue connection is configured.

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
