# Astrokomunita

> Full-stack webová aplikácia na sledovanie vesmírnych udalostí, publikovanie astronomických pozorovaní a prepájanie externých dátových zdrojov s komunitným obsahom.

## O projekte

**Astrokomunita** je názov platformy a tohto repozitára. Projekt vychádza z bakalárskej práce **Nebeský sprievodca 1.0 – Aplikácia na zachytávanie vesmírnych udalostí**, ktorá tvorí jeho akademický základ.

Aplikácia je navrhnutá ako jednotné webové prostredie pre:

- sprístupňovanie astronomických udalostí,
- prácu s komunitným obsahom,
- evidenciu používateľských pozorovaní,
- automatizované spracovanie externých dátových zdrojov,
- správu a publikovanie obsahu v administrátorskej vrstve.

Cieľom projektu je prepojiť informačnú, komunitnú a administrátorskú vrstvu do jedného systému. Na rozdiel od nástrojov zameraných iba na vizualizáciu oblohy alebo izolované dátové výstupy spája Astrokomunita udalosti, obsah, pozorovania a správu údajov v jednom rozhraní.

## Prečo projekt vznikol

Mnohé astronomické aplikácie riešia iba jednu časť problému. Jedna sa sústreďuje na vizualizáciu oblohy, iná na vedecké dáta a ďalšia na spravodajský obsah. Chýba však jednotné prostredie, ktoré by spájalo:

- aktuálne a overené informácie o udalostiach,
- zrozumiteľne spracovaný obsah,
- komunitné zdieľanie pozorovaní,
- automatizované spracovanie externých zdrojov,
- používateľsky prístupné webové rozhranie.

Astrokomunita na tento problém reaguje viacvrstvovou webovou aplikáciou postavenou na modernej architektúre.

## Stav projektu

Repozitár obsahuje implementáciu funkčného prototypu a jeho ďalší vývoj. Jadro systému zahŕňa správu udalostí, komunitný obsah, používateľské pozorovania, administračné workflow a automatizované spracovanie vybraných externých zdrojov.

## Hlavné funkcionality

- prehľad a kalendár astronomických udalostí,
- detail udalosti s praktickými informáciami pre pozorovanie,
- doplnková predpoveď vhodnosti pozorovania pri dostupnej lokalite,
- sledovanie udalostí, plánovanie účasti, pripomienky a pozvánky,
- export udalostí do kalendára vo formáte `ICS`,
- evidencia vlastných pozorovaní vrátane času, miesta, techniky a fotografií,
- komunitné príspevky a používateľské interakcie,
- oddelený komunitný feed a `AstroFeed`,
- bot účty pre pravidelne publikovaný astronomický obsah,
- používateľské profily, onboarding, nastavenia a filtrovacie prvky,
- podpora personalizácie vybraných častí aplikácie podľa lokality a nastavení používateľa,
- administrátorský workflow na zber, kontrolu, preklad, deduplikáciu a publikovanie obsahu,
- realtime notifikácie a živé aktualizácie vybraných častí aplikácie,
- doplnkové moduly, ako sú články, newsletter, záložky, widgety a vyhľadávanie.

## Použitý technologický stack

| Vrstva | Technológie |
| --- | --- |
| Frontend | Vue 3, Vite, Pinia, Vue Router, Axios |
| Backend | Laravel 12, PHP 8.2+, Laravel Sanctum, REST API |
| Realtime komunikácia | Laravel Reverb, Laravel Echo |
| Databáza | MySQL 8.4 |
| Asynchrónne spracovanie | fronty úloh, scheduler, background workery |
| Pomocné služby | Python/FastAPI sky service, moderation service, prekladové a AI služby |
| Nasadenie | Docker, Docker Compose, Caddy |

## Architektúra

Aplikácia je navrhnutá ako viacvrstvový webový systém:

1. Frontend zabezpečuje používateľské rozhranie a komunikáciu s backendom.
2. Backend spracúva aplikačnú logiku, autentifikáciu, správu obsahu a integrácie.
3. Dátová vrstva uchováva aplikačné dáta a vzťahy medzi jednotlivými entitami.
4. Plánované úlohy a background workery zabezpečujú synchronizáciu externých zdrojov a ďalšie asynchrónne operácie.
5. Pomocné služby riešia špecializované úlohy, napríklad astronomické výpočty, moderáciu alebo jazykové spracovanie.
6. Administrátorská vrstva kontroluje kvalitu a životný cyklus externého aj komunitného obsahu.

## Externé dáta a automatizácia

Projekt pracuje s kombináciou externých astronomických zdrojov a interného používateľského obsahu.

V aktuálnej implementácii sa využívajú najmä:

- `NASA RSS`,
- `NASA APOD`,
- `Wikipedia On This Day`,
- vybrané crawling zdroje pre astronomické udalosti,
- import a spracovanie údajov zo zdrojov ako `Astropixels` a `IMO`.

Systém tieto dáta synchronizuje, filtruje, deduplikuje, prekladá a pripravuje na publikovanie v aplikácii. Súčasťou riešenia je aj admin workflow pre kandidátov udalostí pred ich finálnym zverejnením.

## Ukážka

Verejné demo projektu:

- https://astrokomunita.sk

## Lokálne spustenie

Projekt je pripravený na lokálny vývoj pomocou Docker Compose.

### Požiadavky

- Docker
- Docker Compose

### Postup

1. Skopíruj lokálne konfiguračné súbory podľa vzorov v repozitári.
2. Vygeneruj aplikačný kľúč pre backend.
3. Spusť lokálny stack.

```bash
cp backend/.env.example backend/.env
cp frontend/.env.example frontend/.env
docker compose run --rm backend php artisan key:generate
docker compose up -d --build
```

Pri štarte Docker stacku sa v aktuálnej konfigurácii automaticky spúšťajú aj migrácie databázy a inicializačné úlohy backendu.

### Lokálne URL

| Služba | URL |
| --- | --- |
| Frontend | http://127.0.0.1:5174 |
| Backend API | http://127.0.0.1:8001 |
| Mailpit | http://127.0.0.1:8025 |
| Adminer | http://127.0.0.1:8086 |

### Voliteľné

Ak chceš používať funkcionalitu napojenú na `Ollama`, môžeš dodatočne stiahnuť lokálny model:

```bash
docker compose exec ollama ollama pull mistral:latest
```

## Testovanie

```bash
docker compose exec backend php artisan test
docker compose exec frontend npx vitest run
```

## Štruktúra projektu

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
└── docker-compose.prod.yml # produkčné nasadenie
```

## Budúci rozvoj

- rozšírenie vizualizácií astronomických javov,
- interaktívnejšia práca s oblohou a udalosťami,
- rozšírenie mobilného používania a PWA prvkov,
- pokročilejšia personalizácia vybraných častí aplikácie,
- integrácia ďalších dátových zdrojov,
- výkonové optimalizácie a stabilizácia prevádzky,
- rozšírenie komunitných funkcií,
- ďalšie využitie AI pri spracovaní, preklade alebo generovaní obsahu.

## Autor a pôvod projektu

Autorom projektu je **Patrik Rajnoha**.

Projekt vychádza z bakalárskej práce **Nebeský sprievodca 1.0 – Aplikácia na zachytávanie vesmírnych udalostí** na **Univerzite Konštantína Filozofa v Nitre**, Fakulte prírodných vied a informatiky.

Repozitár predstavuje praktickú implementáciu riešenia a jeho ďalší vývoj mimo akademického textu.

## Licencia

Tento repozitár je momentálne zverejnený na prezentačné a akademické účely.

Kým nebude doplnený samostatný súbor `LICENSE`, platia všetky práva vyhradené autorovi.
