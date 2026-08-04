# Agency OS Backend — Windows / PowerShell setup

Windows PowerShell 5.1 does **not** support `&&`. Run each command on its own line, or
join them with `;`. Everything below is PowerShell-ready.

---

## 0. If you already ran `migrate` and hit "table cache already exists"

You copied the overlay onto a fresh `laravel/laravel` skeleton that still has its own
default migrations. They collide with Agency OS' schema. Fix it once:

```powershell
cd "C:\laravel project\agencyos"

# Remove the stock Laravel migrations — Agency OS provides its own users/sessions/cache/jobs
Remove-Item database\migrations\0001_01_01_000000_create_users_table.php -ErrorAction SilentlyContinue
Remove-Item database\migrations\0001_01_01_000001_create_cache_table.php  -ErrorAction SilentlyContinue
Remove-Item database\migrations\0001_01_01_000002_create_jobs_table.php   -ErrorAction SilentlyContinue

# Remove the SQLite file — Agency OS does not run on SQLite
Remove-Item database\database.sqlite -ErrorAction SilentlyContinue

# Confirm only Agency OS migrations remain (should be 15 files)
Get-ChildItem database\migrations | Select-Object Name
```

Expected list: `0001_01_01_000000_create_framework_tables`, then the eight
`2026_07_25_0000xx_*` foundation migrations, then `..._000008` through `..._000014`.

---

## 1. Install PostgreSQL (required — SQLite will not work)

Agency OS uses `jsonb`, partial unique indexes, an `EXCLUDE USING gist` constraint and an
append-only trigger. SQLite silently ignores all of them, producing a database that looks
migrated but enforces nothing. The application now refuses to boot on a non-PostgreSQL
driver rather than pretend.

1. Download the EnterpriseDB installer: <https://www.postgresql.org/download/windows/>
2. Install PostgreSQL 16 (defaults are fine; remember the `postgres` password).
3. Add the binaries to your PATH for this session:

```powershell
$env:Path += ";C:\Program Files\PostgreSQL\16\bin"
psql --version    # verify
```

To make it permanent: *System Properties → Environment Variables → Path → New →*
`C:\Program Files\PostgreSQL\16\bin`

### Create the databases

```powershell
$env:PGPASSWORD = "your-postgres-password"
createdb -U postgres agencyos
createdb -U postgres agencyos_test
psql -U postgres -d agencyos      -c "CREATE EXTENSION IF NOT EXISTS btree_gist;"
psql -U postgres -d agencyos_test -c "CREATE EXTENSION IF NOT EXISTS btree_gist;"
```

> Creating the extension up front avoids needing superuser rights during migration.

---

## 2. Configure `.env`

```powershell
Copy-Item .env.example .env -Force
php artisan key:generate
notepad .env
```

Set these (SQLite lines must be removed or overridden):

```dotenv
APP_TIMEZONE=Africa/Cairo

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=agencyos
DB_USERNAME=postgres
DB_PASSWORD=your-postgres-password

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
```

---

## 3. Run it

```powershell
composer install
php artisan config:clear
php artisan cache:clear
php artisan migrate:fresh --seed
php artisan route:list
php artisan test
.\vendor\bin\pint
php artisan test
```

Then sign in at <http://localhost:8000> after `php artisan serve` —
`manager` / `Demo123!` (see README-BACKEND.md §6 for all accounts).

---

## 4. Troubleshooting

| Symptom | Cause | Fix |
|---|---|---|
| `The token '&&' is not a valid statement separator` | PowerShell 5.1 | one command per line, or use `;` |
| `createdb : not recognized` | PostgreSQL `bin` not on PATH | see step 1 |
| `table "cache" already exists` | stock Laravel migrations still present | see step 0 |
| `Agency OS requires PostgreSQL. Current driver: sqlite` | `.env` still points at SQLite | see step 2 |
| `could not open extension control file "btree_gist"` | `postgresql-contrib` missing | reinstall PostgreSQL with the standard installer (contrib is included) |
| `permission denied to create extension` | database role is not superuser | run the `CREATE EXTENSION` commands in step 1 as `postgres` |
| `SQLSTATE[08006] connection refused` | service not running | `Get-Service postgresql*` then `Start-Service postgresql-x64-16` |
