# Bezpečnostné poznámky

Tento dokument sumarizuje aktuálne bezpečnostné guard-raily v projekte.

## Predvolené hodnoty prostredia

- `docker-compose.yml` (lokálny vývoj) predvolene používa:
  - `APP_ENV=local`
  - `APP_DEBUG=true`
- `docker-compose.prod.yml` (produkčné nasadenie) explicitne používa:
  - `APP_ENV=production`
  - `APP_DEBUG=false`

## Seedovanie predvolených používateľov

- `DefaultUsersSeeder` má produkčné ochrany a v produkcii sa nespustí bez explicitného opt-in.
- Spustenie v produkcii cez `app:seed-default-users` vyžaduje súčasne:
  - parameter `--force`
  - premennú `SEED_DEFAULT_USERS_ENABLED=true`
- Purge používateľov mimo core účtov (`--purge-non-core`) je v produkcii blokovaný.
- Placeholder admin údaje sú v produkcii blokované:
  - placeholder e-mail
  - placeholder heslo
- V `local/testing` sa predvolené účty seedujú štandardne.

## Debug a diagnostické endpointy

- Zastaraný endpoint `/api/csrf-test` bol odstránený.
- Endpointy `/api/debug/auth`, `/api/debug/token` a `/api/notifications/dev-test` sú dostupné iba pri `APP_ENV=local` a `APP_DEBUG=true`.
- Endpoint `/api/observe/diagnostics` je dostupný iba v `local` prostredí.
- Endpointy `/api/health` a `/api/_health` predvolene neexponujú diagnostiku.
- Rozšírená diagnostika pre health endpointy sa zapína iba cez `HEALTH_EXPOSE_DIAGNOSTICS=true`.

## HTTP Security Headers

- Bezpečnostné HTTP hlavičky sú zapnuté predvolene (`SECURITY_HEADERS_ENABLED=true`).
- Middleware pridáva najmä:
  - `X-Frame-Options`
  - `X-Content-Type-Options`
  - `Referrer-Policy`
  - `Permissions-Policy`
  - `X-Permitted-Cross-Domain-Policies`
- `Strict-Transport-Security` (HSTS) sa pridá iba pri secure požiadavke (HTTPS) alebo pri secure session konfigurácii.

## Nahlásenie zraniteľnosti

- Citlivé bezpečnostné nálezy nezverejňuj vo verejnom issue.
- Preferovaný kanál je súkromné nahlásenie cez GitHub Security Advisory (private report) pre tento repozitár.
- V reporte uveď:
  - stručný popis problému
  - kroky na reprodukciu
  - dopad a predpokladanú závažnosť
  - verziu/commit, ktorého sa problém týka
