# Agency OS Routing and Demo Accounts Fix

Date: 30 July 2026

## Routing issue fixed

The approved 51-screen UI was previously exposed from `public/prototype`. Those pages used a separate browser-only LocalStorage login and their links bypassed Laravel authentication and role middleware.

The UI is now stored in `resources/prototype` and served only through:

```text
/app/{screen}
```

`ApprovedUiController` now:

- requires the real Laravel authenticated session;
- checks the real database role before returning every screen;
- prevents URL `?role=` parameters from changing privileges;
- routes relative screen links through Laravel;
- serves prototype assets through the authenticated route;
- logs out using Laravel's CSRF-protected `POST /logout` route;
- blocks Manager access to `task-assign.html` and `task-execute.html` in line with Q21.

Direct public access to `/prototype/*.html` no longer exists.

## Demo accounts

The following accounts are enforced by `DemoSeeder`:

```text
manager  / Demo1234!
tl       / Demo1234!
employee / Demo1234!
```

Run this after replacing the project to update existing local demo accounts:

```bash
php artisan optimize:clear
php artisan db:seed --class=DemoSeeder
```

To rebuild a disposable development database instead:

```bash
php artisan migrate:fresh --seed
```

The second command deletes existing database data.

## Verification completed in the delivery environment

- All 147 project PHP files passed `php -l`.
- `php artisan route:list` completed and includes the protected `approved-ui` route.
- All Blade named-route references resolve.
- All referenced prototype HTML targets exist.
- 51 approved UI screens are present under `resources/prototype`.

The full PHPUnit suite was not run in the delivery container because its PHP installation lacks DOM, mbstring, and XMLWriter, and no PostgreSQL test instance is available. Run locally:

```bash
php artisan test
```
