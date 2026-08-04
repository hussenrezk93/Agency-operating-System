# Agency OS Auth Redesign

Updated on 30 July 2026.

## Changed files

- `resources/views/layouts/auth.blade.php` — new responsive split-screen authentication layout.
- `resources/views/auth/login.blade.php` — redesigned login form with icons and password visibility control.
- `resources/views/auth/forced-password.blade.php` — matched to the new authentication design.
- `lang/en/agencyos.php` — new English interface copy.
- `lang/ar/agencyos.php` — new Arabic interface copy.
- `app/Services/TaskWorkflowService.php` — keeps the previously required fresh lifecycle-state check for on-hold tasks.

## Run after replacing the project

```bash
php artisan optimize:clear
php artisan serve
```

The auth design uses inline CSS and SVG icons, so `npm run dev` is not required for these pages.
