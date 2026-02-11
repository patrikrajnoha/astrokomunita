# Dev Automation (Windows)

One-command local stack for Astrokomunita:
- frontend (Vite) on `http://127.0.0.1:5173`
- backend (Laravel) on `http://127.0.0.1:8000`
- sky microservice (FastAPI) on `http://127.0.0.1:8010`

## Start all

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/dev-up.ps1
```

## Stop all

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/dev-down.ps1
```

## Status + health

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/dev-status.ps1
```

## Optional: start automatically after Windows logon

Install task:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/install-autostart.ps1
```

Remove task:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/uninstall-autostart.ps1
```

Task name: `AstrokomunitaDevStack`
