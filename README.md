# Astrokomunita

> Praktická implementácia webovej aplikácie vytvorenej ako súčasť bakalárskej práce zameranej na sprístupňovanie astronomických udalostí, komunitného obsahu a používateľských pozorovaní v jednotnom webovom prostredí.

## Kontext projektu

Tento repozitár obsahuje zdrojový kód systému **Astrokomunita**, ktorý predstavuje praktickú časť bakalárskej práce **Nebeský sprievodca 1.0 – Aplikácia na zachytávanie vesmírnych udalostí**.

Projekt vznikol ako reakcia na absenciu jednotného webového riešenia, ktoré by v jednom prostredí spájalo:

- informácie o astronomických udalostiach,
- komunitný obsah a používateľské interakcie,
- evidenciu pozorovaní,
- administráciu a publikačné workflow,
- spracovanie vybraných externých dátových zdrojov.

README slúži ako technická sprievodná dokumentácia k implementácii. Nenahrádza text bakalárskej práce, ale dopĺňa ho o praktické informácie potrebné na orientáciu v repozitári, lokálne spustenie a základné testovanie.

## Cieľ a rozsah riešenia

Cieľom systému je vytvoriť viacvrstvovú webovú aplikáciu, ktorá integruje informačnú, komunitnú a administrátorskú vrstvu do jedného rozhrania.

V aktuálnom rozsahu implementácia pokrýva najmä:

- prehľad a kalendár astronomických udalostí,
- detail udalosti s doplnkovými informáciami pre pozorovanie,
- komunitné príspevky a samostatný informačný tok `AstroFeed`,
- evidenciu používateľských pozorovaní,
- používateľské profily, onboarding a nastavenia personalizácie,
- widgetový sidebar prispôsobený podľa preferencií používateľa,
- administračné nástroje na správu udalostí, obsahu a kandidátov,
- automatizované spracovanie vybraných externých zdrojov,
- realtime notifikácie a živé aktualizácie vybraných častí rozhrania.

## Funkčné celky systému

Hlavné funkčné oblasti implementácie sú:

- astronomické udalosti a ich kalendárne zobrazenie,
- používateľské pozorovania vrátane času, miesta, techniky a fotografií,
- komunitný obsah, reakcie a používateľské interakcie,
- personalizácia rozhrania podľa lokality a preferencií,
- administrátorské workflow pre zber, kontrolu, preklad, deduplikáciu a publikovanie obsahu,
- pomocné widgety a doplnkové moduly, napríklad články, newsletter, záložky a vyhľadávanie.

## Architektúra systému

Aplikácia je navrhnutá ako viacvrstvový systém pozostávajúci z týchto častí:

1. **Frontend**
   Používateľské rozhranie implementované ako samostatná SPA aplikácia.
2. **Backend**
   Aplikačná logika, autentifikácia, autorizácia, API vrstva a administrácia.
3. **Dátová vrstva**
   Ukladanie aplikačných dát, vzťahov a prevádzkových záznamov.
4. **Asynchrónne spracovanie**
   Fronty úloh, scheduler a pomocné procesy pre synchronizáciu a spracovanie údajov.
5. **Pomocné služby**
   Samostatné služby pre astronomické výpočty a moderáciu obsahu.
6. **Realtime komunikácia**
   Distribúcia vybraných udalostí a notifikácií v reálnom čase.

## Použité technológie

| Vrstva | Technológie |
| --- | --- |
| Frontend | Vue 3, Vite, Pinia, Vue Router, Axios |
| Backend | Laravel 12, PHP 8.2+, Laravel Sanctum, REST API |
| Realtime komunikácia | Laravel Reverb, Laravel Echo |
| Databáza | MySQL 8.4 |
| Asynchrónne spracovanie | Laravel queue workers, scheduler |
| Pomocné služby | Python/FastAPI sky service, moderation service |
| Nasadenie | Docker, Docker Compose, Caddy |

## Externé dáta a automatizácia

Systém pracuje s kombináciou interného používateľského obsahu a vybraných externých astronomických zdrojov. V aktuálnej implementácii sú využívané najmä:

- `NASA RSS`,
- `NASA APOD`,
- `Wikipedia On This Day`,
- vybrané crawling zdroje pre astronomické udalosti,
- import a spracovanie údajov zo zdrojov ako `Astropixels` a `IMO`.

Tieto dáta sú následne synchronizované, filtrované, deduplikované a pripravované na publikovanie v aplikácii. Súčasťou riešenia je aj administrátorský workflow pre kandidátov udalostí pred ich finálnym zverejnením.

## Štruktúra repozitára

```text
.
├── backend/                # Laravel backend, API, autentifikácia a aplikačná logika
├── frontend/               # Vue frontend a používateľské rozhranie
├── services/sky/           # pomocná služba pre astronomické výpočty
├── moderation-service/     # služba na moderáciu obsahu
├── assets/                 # zdieľané assety a obsahové podklady
├── docs/                   # doplnková dokumentácia
├── scripts/                # pomocné a prevádzkové skripty
├── docker-compose.yml      # lokálne vývojové prostredie
├── docker-compose.prod.yml # produkčné nasadenie
├── Caddyfile               # produkčná reverzná proxy
└── SECURITY.md             # bezpečnostné zásady a postup nahlasovania
```

## Požiadavky na spustenie

Podporované sú dve základné formy lokálneho vývoja:

- vývoj cez Docker Compose,
- natívny vývoj na Windows pomocou pomocných PowerShell skriptov.

### Minimálne požiadavky

- Docker a Docker Compose pre kontajnerový vývoj,
- PHP `^8.2` pre backend,
- Node.js `^20.19.0 || >=22.12.0` pre frontend,
- Python 3 pre `services/sky`,
- `curl` a `docker` v `PATH` pri použití produkčných skriptov.

## Lokálne spustenie cez Docker Compose

Odporúčaná forma lokálneho vývoja je kontajnerové prostredie.

### Postup

1. Skopíruj lokálne konfiguračné súbory podľa vzorov.
2. Vygeneruj aplikačný kľúč backendu.
3. Spusť lokálny stack.

```bash
cp backend/.env.example backend/.env
cp frontend/.env.example frontend/.env
docker compose run --rm backend php artisan key:generate
docker compose up -d --build
```

Pri štarte lokálneho stacku sa v aktuálnej konfigurácii automaticky spúšťajú aj migrácie databázy a inicializačné úlohy backendu.

### Lokálne URL pre Docker režim

| Služba | URL |
| --- | --- |
| Frontend | http://127.0.0.1:5174 |
| Backend API | http://127.0.0.1:8001 |
| Mailpit | http://127.0.0.1:8025 |
| Adminer | http://127.0.0.1:8086 |

### Voliteľné

Ak je potrebné testovať funkcionalitu závislú od `Ollama`, je možné dodatočne stiahnuť lokálny model:

```bash
docker compose exec ollama ollama pull mistral:latest
```

## Alternatívny natívny vývoj na Windows

Repozitár obsahuje aj pomocné skripty pre natívne spustenie frontendovej, backendovej a sky služby mimo Dockeru:

- [scripts/dev-up.ps1](scripts/dev-up.ps1)
- [scripts/dev-status.ps1](scripts/dev-status.ps1)
- [scripts/dev-down.ps1](scripts/dev-down.ps1)

V tomto režime sa štandardne používajú tieto porty:

| Služba | URL |
| --- | --- |
| Frontend | http://127.0.0.1:5173 |
| Backend | http://127.0.0.1:8000 |
| Sky service | http://127.0.0.1:8010 |

## Testovanie

### Testovanie v Docker režime

```bash
docker compose exec backend php artisan test
docker compose exec frontend npx vitest run
```

### Testovanie mimo Dockeru

Frontend:

```bash
cd frontend
npm run test:unit
npm run test:e2e:smoke
npm run lint
```

Backend:

```bash
cd backend
php artisan test
```

## Prevádzkové skripty

Repozitár obsahuje aj skripty určené pre prevádzku a údržbu:

- [scripts/prod-deploy.sh](scripts/prod-deploy.sh) – aktualizácia kódu a nasadenie produkčného stacku,
- [scripts/prod-db-backup.sh](scripts/prod-db-backup.sh) – záloha produkčnej databázy,
- [scripts/prod-db-restore.sh](scripts/prod-db-restore.sh) – obnova databázy zo zálohy,
- [scripts/prod-safe-docker-prune.sh](scripts/prod-safe-docker-prune.sh) – bezpečné čistenie Docker artefaktov.

Táto časť predstavuje prevádzkovú dokumentáciu projektu a nie je jadrom textu bakalárskej práce.

## Bezpečnosť

Bezpečnostné guard-raily a postup pre nahlasovanie zraniteľností sú popísané v [SECURITY.md](SECURITY.md).

## Obmedzenia súčasnej implementácie

Aktuálna implementácia predstavuje funkčný prototyp a vývojovú bázu systému. Z pohľadu akademického a praktického využitia je vhodné počítať s týmito obmedzeniami:

- nie všetky moduly sú navrhnuté ako finálna produkčná verzia,
- časť externých zdrojov závisí od dostupnosti tretích strán,
- výstupy pomocných služieb sú limitované kvalitou vstupných dát a konfigurácie prostredia,
- systém sa priebežne vyvíja, preto sa môže meniť interná štruktúra modulov, testov a prevádzkových skriptov.

## Budúci rozvoj

Pri ďalšom rozvoji systému sa predpokladá najmä:

- rozšírenie vizualizácií astronomických javov,
- rozšírenie mobilného používania a PWA prvkov,
- pokročilejšia personalizácia vybraných častí aplikácie,
- integrácia ďalších dátových zdrojov,
- výkonové optimalizácie a stabilizácia prevádzky,
- rozšírenie komunitných a administračných workflow,
- ďalšie využitie AI pri spracovaní, preklade alebo generovaní obsahu.

## Autor a pôvod projektu

Autorom projektu je **Patrik Rajnoha**.

Projekt vychádza z bakalárskej práce **Nebeský sprievodca 1.0 – Aplikácia na zachytávanie vesmírnych udalostí** na **Univerzite Konštantína Filozofa v Nitre**, Fakulte prírodných vied a informatiky.

Repozitár predstavuje praktickú implementáciu riešenia a jeho ďalší vývoj mimo akademického textu.

## Ukážka nasadenia

Verejne dostupná inštancia projektu:

- https://astrokomunita.sk

## Licencia

Tento repozitár je momentálne zverejnený na prezentačné a akademické účely.

Kým nebude doplnený samostatný súbor `LICENSE`, platia všetky práva vyhradené autorovi.
