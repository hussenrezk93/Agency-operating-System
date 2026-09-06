# Agency OS routing review and stable navigation fix

Reviewed: 30 July 2026

## Defects found in the uploaded build

1. `approved-ui` and `ApprovedUiController` existed, but the claimed seamless router was never injected into any screen.
2. The router expected `localeUrl`, but `APP_LARAVEL_BRIDGE` did not provide it.
3. `public/prototype` still exposed a stale unauthenticated duplicate of all 51 confidential screens.
4. Eleven redirect-only prototype pages caused an unnecessary browser redirect/flash.
5. The repository still contained the stock Laravel welcome page, SQLite file, PHPUnit cache, compiled Blade views and logs.

## Final approach

- `/app/{screen}` remains the only approved UI route.
- Every screen and asset is protected by Laravel authentication and server-side role checks.
- Redirect-only screens are resolved by Laravel before HTML is returned.
- PJAX/DOM replacement was removed. It previously risked broken scripts, stale state and Back/Forward bugs.
- Smoothness now uses native cross-document View Transitions where the browser supports them.
- Internal pages are prefetched on hover/focus/touch, but clicks are never intercepted.
- Unsupported browsers use normal Laravel navigation with no functional difference.
- `public/prototype` was removed.

## Demo accounts

`admin`, `manager`, `tl`, `employee` — password `Demo1234!`

```bash
php artisan optimize:clear
php artisan db:seed --class=DemoSeeder
php artisan test
php artisan serve
```
