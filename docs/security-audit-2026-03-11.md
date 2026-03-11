# Interny Security Audit Report (2026-03-11)

## A. Strucny bezpecnostny prehlad projektu
Projekt (`backend` Laravel API + `frontend` Vue SPA + Python mikroservisy) ma uz implementovane viacere bezpecnostne mechanizmy (Sanctum cookie auth, CSRF flow, middleware pre role/aktivny ucet/verified, viacere rate limitery, server-side validacie a policy/gate enforcement v citlivych castiach). Audit vsak identifikoval realne medzery: chybajuce browser security headers, prilis benevolentny health endpoint metadata output, riziko XSS pri custom HTML widgete, login remember-me nastaveny natvrdo na `true`, a zranitelne zavislosti (`axios`, viacero Composer balikov pred patch updatom).

## B. Zoznam analyzovanych casti systemu
- Backend routing a middleware: `backend/routes/api.php`, `backend/bootstrap/app.php`, `backend/app/Http/Middleware/*`
- Auth/session/account flow: `backend/app/Http/Controllers/Api/AuthController.php`, `PasswordResetController.php`, `AccountEmailController.php`, `config/session.php`, `config/sanctum.php`
- Authorization/policies: `backend/app/Providers/AuthServiceProvider.php`, `backend/app/Policies/*`, admin controllery a route skupiny
- Sidebar/admin HTML widget flow: `backend/app/Support/SidebarWidgetConfigSchema.php`, `backend/app/Http/Controllers/Api/Admin/SidebarCustomComponentController.php`, `frontend/src/components/widgets/SidebarWidgetRenderer.vue`
- Upload/media flow: `ProfileController`, `AdminUserController`, `MediaStorageService`, `PublicMediaFileController`
- Frontend auth/API flow: `frontend/src/services/api.js`, `frontend/src/stores/auth.js`, router guards
- Supply chain: `backend/composer.json|lock`, `frontend/package.json|package-lock.json`, audity `composer audit`, `npm audit --omit=dev`
- Test coverage pre security oblasti: `backend/tests/Feature/*`, `backend/tests/Unit/*`, `frontend` vitest suite

## C + D. Security checklist (stav viazany na realny projekt)
| Oblast | Stav | Poznamka / dokaz |
|---|---|---|
| Login/register/reset flow | OK | Route throttling + server-side validacie (`/api/auth/*`), code-based reset flow (`PasswordResetController`) |
| Session regeneration po login | OK | `AuthController@login` vola `$request->session()->regenerate()` |
| Remember-me spravanie | Riziko (opraveno) | Povodne `Auth::attempt(..., true)` natvrdo; fix v `AuthController@login` |
| CSRF pre SPA cookie auth | OK | Sanctum stateful + `withXSRFToken`/`withCredentials`, `EnsureFrontendRequestsAreStateful` |
| Role-based admin ochrana | OK | `admin`, `admin.content`, `active`, `verified` middleware na route skupinach |
| Policy/gate enforcement pre citlive akcie | Ciastocne | Vo vacsine endpointov je pouzite; potrebne priebezne pokryvat nove endpointy |
| Input validacie (backend) | Ciastocne | Silne vo vacsine requestov; URL sanitizacia v sidebar schema bola benevolentna (opraveno) |
| XSS ochrana UGC (Vue default escaping) | OK | Bezne textove rendery pouzivaju mustache escaping |
| XSS pri custom HTML widgete | Riziko (opraveno) | Povodny regex sanitizer bol obiditelny; nahradeny DOM allowlist sanitizerom |
| SQL injection | Ciastocne | Caste `raw` dotazy su vacsinou staticke/bindovane; treba drzat pravidlo bez user-controlled raw fragmentov |
| Upload bezpecnost | Ciastocne | Validacie MIME/ext/size su implementovane; odporucane priebezne doplnat AV scanning pre produkciu |
| Rate limiting anti-abuse | Ciastocne | Viac limiterov existuje (`auth-login`, `report-submissions`, `admin-ai`...), nie vsetky business akcie maju dedikovane limity |
| Browser security headers | Chyba (opraveno) | Pridany globalny middleware `AddSecurityHeaders` |
| CORS konfiguracia | OK | Allowlist cez `CORS_ALLOWED_ORIGINS`, credentials true |
| Health/information leak | Riziko (opraveno) | `/api/health` a `/_health` defaultne odhalovali env/build metadata |
| Error handling v produkcii | OK | Generic 500 pri `APP_DEBUG=false` v `bootstrap/app.php` |
| Secrets/.env hygiene | OK | `.env` nie je trackovany, pouziva sa `.env.example` |
| Mass assignment ochrana modelov | Ciastocne | `User::$fillable` je rozsiahly; aktualne endpointy vacsinou validuju explicitne payloady |
| Frontend token storage | OK | SPA nepouziva localStorage pre auth tokeny (cookie-based flow) |
| Dependency vulnerabilities (frontend prod) | Riziko (opraveno) | `axios` zranitelna verzia -> update na `^1.13.6` |
| Dependency vulnerabilities (backend) | Riziko (opraveno) | Laravel/Symfony/CommonMark/Psy/PHPUnit patch updates; `composer audit` cisty |

## E. Problemy, dopad, priorita, odporucana naprava
1. Chybajuce security headers (Priorita: High)  
- Dopad: Vyssie riziko clickjacking, MIME sniffing a slabe browser-level policy defaults.  
- Stav: Opravene (`backend/app/Http/Middleware/AddSecurityHeaders.php`, `backend/config/security.php`, `backend/bootstrap/app.php`).

2. XSS riziko v admin HTML widgete (Priorita: High)  
- Dopad: Pri kompromitovanom admin konte alebo zlomyselnej konfiguracii mohol byt do sidebaru vlozeny skodlivy obsah renderovany cez `v-html`.  
- Stav: Opravene DOM allowlist sanitizaciou + safe href enforcement (`backend/app/Support/SidebarWidgetConfigSchema.php`) a safe rel v UI (`frontend/src/components/widgets/SidebarWidgetRenderer.vue`).

3. Information leak cez health endpointy (Priorita: Medium)  
- Dopad: Expozicia `env`, `git_sha`, `build_id` pre neautorizovanych klientov zjednodusuje recon.  
- Stav: Opravene; metadata su defaultne vypnute, zapinatelne cez `HEALTH_EXPOSE_DIAGNOSTICS` (`backend/routes/api.php`, `backend/.env.example`).

4. Forced remember-me pri login (Priorita: Medium)  
- Dopad: Zbytocne predlzena session persistencia pre kazde prihlasenie.  
- Stav: Opravene; remember je explicitny optional bool input (`backend/app/Http/Controllers/Api/AuthController.php`).

5. Vulnerable dependencies (Priorita: High pre prod deps)  
- Dopad: Exploitovatelne kniznice mozu oslabit aplikaciu mimo vlastneho kodu.  
- Stav: Opravene (`frontend/package.json|lock`, `backend/composer.lock`).

## F. Quick wins
- Centralizovane security headers cez jeden middleware.
- Vypnutie health diagnostik defaultne (least disclosure).
- Explicitny `remember` flag namiesto forced persistence.
- Immediate patch aktualizacie zavislosti (`axios`, Laravel/Symfony/CommonMark/Psy/PHPUnit).
- Rozsirene testy pre security regressions.

## G. Zoznam kritickych problemov
- Nebol najdeny aktivne exploatovatelny kriticky RCE/SQLi v auditovanom scope.
- Najvaznejsie realne rizika boli: XSS surface v HTML widgete a supply-chain CVE (pred opravou).

## H. Odporucania vhodne aj do bakalarskej prace
- Formalizovat "security baseline" ako povinne middleware+config policy (headers, health disclosure, session strategy).
- Zavest periodicky dependency management proces (`composer audit`, `npm audit --omit=dev`) ako cast CI.
- Udrziavat "defense in depth": validacia + sanitizacia + output escaping.
- Dokumentovat authorization matrix (role x endpoint x policy) a testovat ju automatizovane.
- Pre produkciu zvazit doplnenie CSP politiky presne podla nasadenia frontendu a CDN.

## I. Prehlad implementovanych oprav
- Pridane security headers middleware + konfiguracia:  
  `backend/app/Http/Middleware/AddSecurityHeaders.php`  
  `backend/config/security.php`  
  `backend/bootstrap/app.php`  
  `backend/.env.example`
- Sprisnene health endpointy (default no diagnostics):  
  `backend/routes/api.php`
- Opraveny login remember flow:  
  `backend/app/Http/Controllers/Api/AuthController.php`
- Opravena HTML sanitizacia a link hardening:  
  `backend/app/Support/SidebarWidgetConfigSchema.php`  
  `frontend/src/components/widgets/SidebarWidgetRenderer.vue`
- Dependency updates:  
  `frontend/package.json`, `frontend/package-lock.json`  
  `backend/composer.lock`

## J. Zoznam doplnenych/upravenych testov
- Nove:
  - `backend/tests/Feature/SecurityHeadersTest.php`
  - `backend/tests/Unit/SidebarWidgetConfigSchemaTest.php`
- Upravene:
  - `backend/tests/Feature/HealthEndpointTest.php`
  - `backend/tests/Feature/AuthLoginTest.php`
  - `backend/tests/Feature/SidebarCustomComponentTest.php`
- Overenia po implementacii:
  - `php artisan test --filter="(HealthEndpointTest|SecurityHeadersTest|AuthLoginTest|SidebarCustomComponentTest|SidebarWidgetConfigSchemaTest)"` (PASS)
  - `npm run test:unit` vo `frontend` (PASS)
  - `composer audit` (PASS)
  - `npm audit --omit=dev` (PASS)
