# 🧪 API Testovanie na Windows PowerShell

## ❌ Problém
Štandardné Linux príkazy nefungujú na Windows PowerShell:
```bash
# ❌ NEFUNGUJE na Windows
curl "http://localhost:8000/api/search/users?q=test" | head -20
```

## ✅ Správne príkazy pre Windows

### 1. **PowerShell (odporúčaný)**
```powershell
# Základný test
Invoke-RestMethod -Uri "http://127.0.0.1:8000/api/search/users?q=a" -Method GET

# Skratka
irm "http://127.0.0.1:8000/api/search/users?q=a"

# S formátovaným JSON výstupom
(irm "http://127.0.0.1:8000/api/search/users?q=a") | ConvertTo-Json -Depth 10

# Iba prvých 5 výsledkov
(irm "http://127.0.0.1:8000/api/search/users?q=a") | Select-Object -First 5
```

### 2. **True curl (ak je nainštalovaný)**
```powershell
# Použiť curl.exe namiesto curl
curl.exe "http://127.0.0.1:8000/api/search/users?q=a"
```

### 3. **Testovací skript**
Spustiť: `.\test-api.ps1`
```powershell
# Alebo použiť priamo:
irm "http://127.0.0.1:8000/api/search/users?q=a"
irm "http://127.0.0.1:8000/api/search/posts?q=test"
irm "http://127.0.0.1:8000/api/trending"
irm "http://127.0.0.1:8000/api/hashtags"
irm "http://127.0.0.1:8000/api/hashtags/test/posts"
```

## 🎯 Funkčné API Endpointy

### Search & Discovery
- ✅ `GET /api/search/users?q=<query>` - Vyhľadávanie používateľov
- ✅ `GET /api/search/posts?q=<query>` - Vyhľadávanie príspevkov

### Hashtags
- ✅ `GET /api/hashtags` - Zoznam všetkých hashtagov
- ✅ `GET /api/hashtags/{name}/posts` - Príspevky s hashtagom
- ✅ `GET /api/trending` - Trending hashtagy

### Recommendations (vyžaduje auth)
- ✅ `GET /api/recommendations/users` - Odporúčané účty
- ✅ `GET /api/recommendations/posts` - Odporúčané príspevky

## 💡 Tipy pre Windows PowerShell
- `irm` = skratka pre `Invoke-RestMethod`
- `curl` v PowerShell = alias pre `Invoke-WebRequest` (nie je totožný s Linux curl)
- Používajte `curl.exe` pre true curl príkaz
- `Select-Object -First N` namiesto `head -N`
- `ConvertTo-Json` pre formátovaný výstup

## 🚀 Rýchle testovanie
```powershell
# Spustiť backend server
php artisan serve --host=127.0.0.1 --port=8000

# V novom PowerShell okne testovať:
irm "http://127.0.0.1:8000/api/health"
irm "http://127.0.0.1:8000/api/search/users?q=a"
irm "http://127.0.0.1:8000/api/trending"
```
