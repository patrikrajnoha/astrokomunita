# bezpečnostné poznámky

## bezpečné predvolené hodnoty

- `docker-compose.yml` predvolene používa `APP_ENV=production` a `APP_DEBUG=false`, pokiaľ nie je explicitne prepísané.
- lokálny vývoj môže zapnúť tieto hodnoty cez premenné prostredia (napríklad `APP_ENV=local` a `APP_DEBUG=true`).

## počiatočné dáta

- `DefaultUsersSeeder` je obmedzený iba na prostredia `local` a `testing`.
- predvolené prihlasovacie údaje (napríklad `admin/admin`) sa nikdy neseedujú mimo local/testing.

## ladiace trasy

- zastaraný endpoint `/api/csrf-test` bol odstránený.
- existujúce ladiace trasy sú naďalej chránené kontrolou `local + APP_DEBUG`.
