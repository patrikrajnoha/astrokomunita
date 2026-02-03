# Implementácia Pinned Posts (Pripnutých príspevkov)

## Prehľad riešenia

Implementácia podpory pre pripnuté príspevky, ktoré sa vždy zobrazujú ako prvé vo feede pre všetkých používateľov (prihlásených aj neprihlásených).

## Architektúra riešenia

### 1. Databáza
- **Stĺpec**: `pinned_at` (timestamp, nullable) - už existuje v migrácii `2026_02_02_191839_add_pinned_at_to_posts_table.php`
- **Index**: `pinned_at` pre efektívne query
- **Logika**: `NULL` = nie je pripnutý, `timestamp` = pripnutý (čas pripnutia)

### 2. Backend (Laravel)

#### Nový endpoint: `GET /api/feed`
- Kombinuje user posts + AstroBot posts
- Pinned posts sa zobrazujú ako prvé (oba typy)
- Paginácia zachovaná - pinned posts sa nepočítajú do paginácie
- Podporuje všetky existujúce filtre (tag, kind, atď.)

#### Admin funkcie (už existujú):
- `PATCH /api/admin/posts/{id}/pin` - pripnutie príspevku
- `PATCH /api/admin/posts/{id}/unpin` - odpinutie príspevku
- Transakčné spracovanie - automaticky odpína predchádzajúce pinned posts

#### Query logika:
```sql
-- Pinned posts first
WHERE pinned_at IS NOT NULL ORDER BY pinned_at DESC

-- Regular posts (excluding pinned)
WHERE pinned_at IS NULL ORDER BY created_at DESC
```

### 3. Frontend (Vue 3)

#### Zmeny v `FeedList.vue`:
- Nový endpoint `/feed` pre "Pre vás" tab
- Vizuálne označenie pinned posts:
  - Badge "📌 Pripnuté"
  - Špeciálne styling (oranžová farba, border)
- Admin tlačidlo pre pin/unpin akcie

#### CSS štýly:
- `.post-card--pinned` - špeciálny vzhľad
- `.pinned-badge` - badge s ikonou
- Gradient pozadie a border pre vizuálny kontrast

## Technické zdôvodnenie návrhu

### 1. Centralizovaná logika na backend
- **Prednosť**: Konzistentné správanie pre všetkých klientov
- **Bezpečnosť**: Autorizácia a validácia na jednom mieste
- **Údržba**: Zmeny v logike nie sú potrebné vo fronte

### 2. Timestamp namiesto boolean
- **Výhoda**: Uchováva informáciu o čase pripnutia
- **Budúcnosť**: Možnosť implementácie "pin expiration"
- **Ordering**: Prirodzené zoradenie podľa času pripnutia

### 3. Dva oddelené query
- **Výkon**: Efektívne využitie indexov
- **Jednoduchosť**: Clear separation medzi pinned a regular posts
- **Paginácia**: Pinned posts nemenia počet stránok

### 4. Transakčné spracovanie
- **Konzistencia**: Zabraňuje viacerým pinned posts naraz
- **Race conditions**: Atomicita operácií pin/unpin
- **Data integrity**: Zabezpečuje konzistentný stav

## API Endpoints

### Public
- `GET /api/feed` - Hlavný feed s pinned posts
- `GET /api/feed/astrobot` - AstroBot feed (pinned podpora)

### Admin
- `PATCH /api/admin/posts/{id}/pin` - Pripnutie príspevku
- `PATCH /api/admin/posts/{id}/unpin` - Odpinutie príspevku

## Frontend komponenty

### Vizuálne prvky
- 📌 ikona pre pinned posts
- Oranžová farebná schéma pre odlíšenie
- Admin tlačidlo pre pin/unpin akcie

### UX
- Pinned posts vždy na vrchu feedu
- Zachovanie existujúcej paginácie
- Bezproblémová integrácia s existujúcimi filtrami

## Testovanie

### Scenáre
1. **Pin post**: Admin pripne post → zobrazí sa ako prvý
2. **Unpin post**: Admin odpine post → stratí sa prvé miesto
3. **Multiple pins**: Nový pin automaticky odpína starý
4. **Pagination**: Pinned posts nemenia počet stránok
5. **Filters**: Filtre fungujú s pinned posts

### Edge cases
- Pinned AstroBot post
- Pinned post s tagom
- Pinned post v replies/media feed
- Race condition pri pinovaní

## Budúce rozšírenia

### Možnosti
- **Pin expiration**: Automatické odpinutie po čase
- **Multiple pins**: Limit počtu pinned posts
- **Categorized pins**: Rôzne typy pinned posts
- **User pins**: Používateľské pripnutie (nie len admin)

### Implementácia
- Všetky rozšírenia sú možné bez zmeny existujúcej štruktúry
- `pinned_at` timestamp poskytuje flexibilitu
- Centralizovaná logika uľahčuje modifikácie

## Záver

Riešenie poskytuje robustnú, škálovateľnú a udržiavateľnú implementáciu pinned posts:
- **Globálne** pre všetkých používateľov
- **Predvídateľné** poradie a správanie
- **Jednoduché** na vysvetlenie a implementáciu
- **Extensible** pre budúce požiadavky
