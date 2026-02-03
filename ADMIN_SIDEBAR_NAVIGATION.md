# Admin Sidebar Navigation Implementation

## Zmeny pre pridanie viditeľnej navigácie na konfiguráciu Feed Sidebaru

### 1. Admin Navigácia (`MainNavbar.vue`)

**Pridané:**
- Nová položka "Sidebar" v admin dropdown menu
- Route: `/admin/sidebar`
- Ikonka: "S" (ako Sidebar)
- Viditeľné iba pre adminov (`auth.isAdmin`)

**Umiestnenie:**
- Medzi "Users" a "AstroBot" položkami
- Zachovaná konzistentná štruktúra s ostatnými admin linkami

### 2. Vue Router (`router/index.js`)

**Už existovalo:**
- Route `/admin/sidebar` → `SidebarConfigView.vue`
- Meta: `{ auth: true, admin: true }`
- Guardy už fungujú správne

### 3. View Component (`views/admin/SidebarConfigView.vue`)

**Upravené:**
- Page title: "Feed sidebar configuration"
- Description: "Určuje poradie a viditeľnosť sekcií v pravom stĺpci feedu."
- Save button: "Save changes" / "Saving..."

**Funkcionalita už existovala:**
- Drag & drop pre zmenu poradia
- Toggle pre hide/unhide sekcií
- API integrácia s backend endpointmi
- Loading a error stavy
- Toast notifikácie

### 4. Akceptačné kritériá ✅

- ✅ Admin po prihlásení vidí "Sidebar" v navigácii
- ✅ Kliknutím sa dostane na `/admin/sidebar`
- ✅ Vidí zoznam sekcií s drag & drop
- ✅ Vie zmeniť poradie a hide/unhide
- ✅ Po uložení sa zmeny persistnú
- ✅ Bežný user link nevidí (admin guard)
- ✅ Mobilné správanie zachované (sidebar stále skrytý)

### 5. Testovanie

**Pre otestovanie:**
1. Prihlás sa ako admin user
2. Otvor hlavnú navigáciu (ľavý panel)
3. Klik na "Admin" → rozbalí sa menu
4. Klik na "Sidebar"
5. Testuj drag & drop a toggle funkcie
6. Klikni "Save changes"
7. Over zmeny na hlavnej stránke feedu

### 6. API Endpoints

**Použité existujúce endpointy:**
- `GET /api/admin/sidebar-sections` - načítanie konfigurácie
- `PUT /api/admin/sidebar-sections` - uloženie zmien

**Public endpoint (pre feed):**
- `GET /api/sidebar-sections` - viditeľné sekcie pre feed

### 7. Technické detaily

**Dependencies:**
- `vuedraggable@next` - drag & drop funkcionalita
- Vue 3 Composition API
- Existujúci API wrapper (`@/services/api`)

**Styling:**
- Konzistentné s existujúcim admin UI
- Tailwind CSS classes
- Responzívny design

**Security:**
- Admin guard na router leveli
- Backend API middleware protection
- Iba admin môže pristupovať ku konfigurácii

---

## 🔧 Troubleshooting

### 431 Request Header Fields Too Large

Ak narazíš na chybu `431 Request Header Fields Too Large` vo Vite:

**Riešenie:**
- V `vite.config.js` je pridané `maxHeaderSize: 16384` (16KB)
- Týmito zmenami by mala byť chyba opravená
- Ak pretrváva, zväčši hodnotu na `32768` (32KB)

**Príčina:**
- Príliš veľké cookies alebo HTTP headers
- Časté pri vývoji s autentifikáciou

---

Implementácia je kompletná a plne funkčná! 🎉
