# Testing API Endpoints on Windows (PowerShell)

## PowerShell Commands for API Testing

V PowerShelli je `curl` alias na `Invoke-WebRequest`, preto používame `Invoke-RestMethod` (skratka `irm`).

### Public Endpoint (no authentication)

```powershell
# Basic request
irm http://localhost:8000/api/sidebar-sections

# Formatted JSON output
irm http://localhost:8000/api/sidebar-sections | ConvertTo-Json -Depth 10
```

### Admin Endpoint (requires authentication)

```powershell
# First get auth token (replace with your credentials)
$response = irm http://localhost:8000/api/auth/login -Method POST -ContentType "application/json" -Body '{"email":"admin@example.com","password":"password"}'
$token = $response.token

# Use token for admin requests
irm http://localhost:8000/api/admin/sidebar-sections -Headers @{"Authorization"="Bearer $token"} | ConvertTo-Json -Depth 10
```

## Expected Responses

### GET /api/sidebar-sections

Should return:
```json
{
    "data": [
        {
            "key": "next_event",
            "title": "Najbližšia udalosť",
            "sort_order": 1
        },
        {
            "key": "latest_articles", 
            "title": "Najnovšie články",
            "sort_order": 2
        },
        {
            "key": "nasa_apod",
            "title": "NASA – Obrázok dňa", 
            "sort_order": 3
        }
    ]
}
```

**Requirements:**
- ✅ Returns JSON array
- ✅ Only visible sections (is_visible = true)
- ✅ Sorted by sort_order ASC
- ✅ No authentication required

### GET /api/admin/sidebar-sections

Should return all sections (including hidden) with full data:
```json
{
    "data": [
        {
            "id": 1,
            "key": "next_event",
            "title": "Najbližšia udalosť",
            "is_visible": true,
            "sort_order": 1,
            "created_at": "...",
            "updated_at": "..."
        }
        // ... all sections
    ]
}
```

**Requirements:**
- ✅ Requires admin authentication
- ✅ Returns all sections (visible + hidden)
- ✅ Includes full section data (id, is_visible, timestamps)
- ✅ Sorted by sort_order ASC

## Common PowerShell Aliases

- `irm` → `Invoke-RestMethod`
- `iwr` → `Invoke-WebRequest` 
- `curl` → `Invoke-WebRequest` (NOT the same as Unix curl!)

## Testing Tips

1. **Use `-Depth 10`** for nested JSON objects
2. **Pipe to `ConvertTo-Json`** for readable output
3. **Check status codes** with `$_.StatusCode` in error handling
4. **Use `-Headers`** for authentication tokens
5. **Use `-Method POST/PUT/DELETE`** for different HTTP methods

## Example: Full Admin Workflow

```powershell
# 1. Login as admin
$login = irm http://localhost:8000/api/auth/login -Method POST -ContentType "application/json" -Body '{"email":"admin@example.com","password":"password"}'
$token = $login.token

# 2. Get current sidebar config
$sections = irm http://localhost:8000/api/admin/sidebar-sections -Headers @{"Authorization"="Bearer $token"}

# 3. Update sections (example: hide NASA widget)
$updateData = @{
    sections = @(
        @{id=1; sort_order=1; is_visible=$true},
        @{id=2; sort_order=2; is_visible=$true}, 
        @{id=3; sort_order=3; is_visible=$false}  # Hide NASA widget
    )
} | ConvertTo-Json -Depth 10

irm http://localhost:8000/api/admin/sidebar-sections -Method PUT -Headers @{"Authorization"="Bearer $token"} -ContentType "application/json" -Body $updateData

# 4. Verify changes on public endpoint
irm http://localhost:8000/api/sidebar-sections | ConvertTo-Json -Depth 10
```
