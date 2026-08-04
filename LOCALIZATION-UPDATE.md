# Agency OS Arabic / English UI Update

## Implemented

- Persistent Arabic / English language switch on guest and authenticated screens.
- Arabic uses `dir="rtl"`; English uses `dir="ltr"`.
- The switch is deliberately laid out as **English on the left** and **العربية on the right**.
- Selected language is saved in the session and in a one-year cookie, so it survives refresh and sign-out.
- Arabic is the default for new installations through `.env.example`.
- Login, forced password, dashboard, validation/auth messages and demo content are localized.
- Demo users, departments, clients, projects, tasks, statuses and priorities follow the active language.
- Existing real entity values are not overwritten; only known development/mock values receive localized display labels.
- Orange (`#F97316`) and white (`#FFFFFF`) are the two primary identity colors.
- Shared CSS/Tailwind design tokens were added for future screens.
- Laravel requirement was aligned to `^12.0`, matching the locked Laravel `v12.64.0`; the lock content hash was updated.

## New files

- `app/Http/Controllers/LocaleController.php`
- `app/Http/Middleware/SetLocale.php`
- `app/Support/LocalizedDemoData.php`
- `tests/Feature/LocalizationTest.php`

## Important verification

Static and smoke verification completed in the packaging environment:

- PHP syntax checks passed for all created/modified PHP files.
- Blade templates compiled to valid PHP.
- `/locale` route is registered.
- HTTP smoke test passed: English `LTR` → switch → Arabic `RTL`.
- Locale-aware demo data check passed in both languages.

The full PostgreSQL PHPUnit suite could not be executed in the packaging container because its PHP runtime lacks `dom`, `mbstring`, and `xmlwriter`. Run locally in the existing Agency OS environment:

```powershell
php artisan config:clear
php artisan test
```

The update adds three localization tests, so the executed test count should increase by three when the complete suite is run.
