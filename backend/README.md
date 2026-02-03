<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## AstroKomunita Project

## Notifications System (Bachelor Thesis)

### ERD (text)
- notifications: id, user_id (FK users.id), type, data (json), read_at, created_at, updated_at
  - indexes: (user_id, read_at), (user_id, created_at), (user_id, type)
- notification_events: id, hash (unique), notification_id (FK notifications.id), created_at, updated_at

### Use-case scenáre
1. Post like
   - používateľ B lajkuje post používateľa A → vznikne notifikácia typu `post_liked`
   - deduplikácia: pre (recipient, actor, post) max 1 unread notifikácia, pri opakovaní sa len bumpne čas
2. Event reminder
   - scheduler nájde eventy štartujúce v okne T-60
   - vytvorí `event_reminder` notifikáciu pre subscriberov alebo globálne pre všetkých aktívnych userov
   - idempotencia cez `notification_events.hash`

### Scheduler (lokálne)
```powershell
php artisan schedule:work
```

### API endpoints
- GET /api/notifications (page, per_page)
- GET /api/notifications/unread-count
- POST /api/notifications/{id}/read
- POST /api/notifications/read-all

### API Testing on Windows (PowerShell)

**Important:** This project runs on Windows + PowerShell. For API testing, use PowerShell commands instead of Unix-style `curl | jq`.

```powershell
# Public endpoint
irm http://localhost:8000/api/sidebar-sections | ConvertTo-Json -Depth 10

# Admin endpoint (requires auth)
irm http://localhost:8000/api/admin/sidebar-sections -Headers @{"Authorization"="Bearer YOUR_TOKEN"} | ConvertTo-Json -Depth 10
```

📖 **Full documentation:** See `API_TESTING.md` for complete testing guide.

> **Note:** In PowerShell, `curl` is an alias for `Invoke-WebRequest`, so we use `Invoke-RestMethod` (irm) for API calls.

### Scheduler (local dev)

For local development on Windows, run the scheduler worker in a separate terminal so AstroBot cleanup runs automatically:

```powershell
php artisan schedule:work
```

To test the purge command manually:

```powershell
php artisan astrobot:purge-old-posts --dry-run
```

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
