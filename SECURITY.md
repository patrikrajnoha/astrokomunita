# Security Notes

## Safe Defaults
- `docker-compose.yml` now defaults to `APP_ENV=production` and `APP_DEBUG=false` unless explicitly overridden.
- Local development can opt in with environment overrides (for example `APP_ENV=local` and `APP_DEBUG=true`).

## Seed Data
- `DefaultUsersSeeder` is hard-gated to `local` and `testing` environments.
- Default credentials (such as `admin/admin`) are never seeded outside local/testing.

## Debug Routes
- The legacy `/api/csrf-test` endpoint has been removed.
- Existing debug routes remain behind the existing `local + APP_DEBUG` checks.