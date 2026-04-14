# Bezpečnostné zásady projektu

Tento dokument sumarizuje základné bezpečnostné opatrenia, ktoré sú v projekte **Astrokomunita** implementované na úrovni konfigurácie, backendu a prevádzkových guard-railov.

Dokument má sprievodný charakter. Neslúži ako formálny bezpečnostný audit ani ako úplná prevádzková bezpečnostná príručka, ale ako prehľad aktuálne zavedených mechanizmov a odporúčaného spôsobu nahlasovania zraniteľností.

## Rozsah dokumentu

Dokument sa vzťahuje najmä na:

- bezpečnostné rozdiely medzi lokálnym a produkčným prostredím,
- ochrany pri seedovaní predvolených používateľov,
- obmedzenie debug a diagnostických endpointov,
- základné HTTP bezpečnostné hlavičky,
- spôsob nahlasovania zraniteľností.

## Prostredia a predvolené hodnoty

Projekt odlišuje lokálne vývojové prostredie od produkčného nasadenia.

### Lokálny vývoj

Konfigurácia v `docker-compose.yml` predvolene používa:

- `APP_ENV=local`
- `APP_DEBUG=true`

Táto konfigurácia je určená na lokálny vývoj a testovanie. Nie je určená na verejné nasadenie.

### Produkčné prostredie

Konfigurácia v `docker-compose.prod.yml` predvolene používa:

- `APP_ENV=production`
- `APP_DEBUG=false`

V produkcii sa predpokladá reverzná proxy cez `Caddy` a HTTPS terminácia na verejných doménach projektu.

## Seedovanie predvolených používateľov

Projekt obsahuje mechanizmus seedovania predvolených účtov, ktorý je chránený proti neúmyselnému použitiu v produkcii.

### Ochranné pravidlá

- `DefaultUsersSeeder` sa v produkcii nespustí bez explicitného opt-in.
- Príkaz `php artisan app:seed-default-users --force` vyžaduje zároveň:
  - parameter `--force`
  - premennú prostredia `SEED_DEFAULT_USERS_ENABLED=true`
- Mazanie používateľov mimo základných účtov pomocou `--purge-non-core` je v produkcii blokované.
- Seedovanie s placeholder administrátorským e-mailom alebo heslom je v produkcii blokované.
- V prostrediach `local` a `testing` je seedovanie predvolených účtov povolené štandardne.

Tieto obmedzenia znižujú riziko neúmyselného vytvorenia nevhodných alebo testovacích účtov v produkčnom prostredí.

## Debug a diagnostické endpointy

Debug a vývojové endpointy sú dostupné len v obmedzenom rozsahu a nie sú určené na produkčné používanie.

### Vývojové endpointy

Nasledujúce endpointy sú dostupné iba pri kombinácii `APP_ENV=local` a `APP_DEBUG=true`:

- `/api/debug/auth`
- `/api/debug/token`
- `/api/notifications/dev-test`

Endpoint:

- `/api/observe/diagnostics`

je obmedzený na `local` prostredie.

Zastaraný endpoint:

- `/api/csrf-test`

bol z API odstránený.

### Health endpointy

Projekt používa endpointy:

- `/api/health`
- `/api/_health`

Tieto endpointy predvolene nevracajú rozšírenú diagnostiku. Dodatočné diagnostické údaje sa sprístupnia len pri explicitnom zapnutí:

- `HEALTH_EXPOSE_DIAGNOSTICS=true`

Tým sa obmedzuje riziko zverejnenia interných prevádzkových informácií bez vedomého zásahu správcu prostredia.

## HTTP bezpečnostné hlavičky

Backend používa middleware pre pridávanie základných HTTP bezpečnostných hlavičiek. Tento mechanizmus je predvolene zapnutý cez:

- `SECURITY_HEADERS_ENABLED=true`

### Predvolene nastavované hlavičky

Middleware pridáva najmä:

- `X-Frame-Options`
- `X-Content-Type-Options`
- `Referrer-Policy`
- `Permissions-Policy`
- `X-Permitted-Cross-Domain-Policies`

Hlavička:

- `Strict-Transport-Security`

sa pridáva iba pri secure požiadavke, teda typicky pri HTTPS, alebo pri konfigurácii secure session.

## Prenos a verejné rozhranie

Produkčné nasadenie predpokladá oddelenie verejných vstupných bodov na:

- webovú aplikáciu,
- API vrstvu,
- websocket komunikáciu.

Verejné domény sú obsluhované cez `Caddy`, ktorý zabezpečuje HTTPS termináciu. Z pohľadu bezpečnosti to znamená, že verejné rozhranie projektu je navrhnuté s dôrazom na šifrovaný prenos a oddelenie jednotlivých služieb.

## Odporúčania pre prevádzku

Pri nasadení mimo lokálneho vývoja je vhodné dodržať minimálne tieto zásady:

- nepoužívať `APP_DEBUG=true` v produkcii,
- neexponovať vývojové a interné endpointy mimo kontrolovaného prostredia,
- nepoužívať placeholder hodnoty pre administrátorské účty ani interné tokeny,
- ponechať rozšírenú health diagnostiku vypnutú, ak nie je potrebná,
- používať HTTPS na všetkých verejných vstupných bodoch,
- citlivé konfiguračné súbory a produkčné `.env` hodnoty neuchovávať vo verejnom repozitári.

## Nahlásenie zraniteľnosti

Citlivé bezpečnostné nálezy nezverejňuj vo verejnom issue.

Preferovaný spôsob nahlásenia je súkromné nahlásenie cez **GitHub Security Advisory** alebo iný neveľejný komunikačný kanál dohodnutý s autorom projektu.

V reporte odporúčame uviesť:

- stručný popis problému,
- kroky na reprodukciu,
- očakávané a skutočné správanie,
- predpokladaný dopad a odhad závažnosti,
- verziu, vetvu alebo commit, ktorého sa problém týka,
- informáciu, či je chyba reprodukovateľná v lokálnom alebo produkčnom prostredí.

## Poznámka k akademickému charakteru projektu

Projekt je vyvíjaný ako praktická implementácia bakalárskej práce a zároveň ako pokračujúca vývojová báza. Bezpečnostné opatrenia preto treba vnímať ako priebežne rozširovanú súčasť systému, nie ako uzavretý a finálne auditovaný bezpečnostný model.
