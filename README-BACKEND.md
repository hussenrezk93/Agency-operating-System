> **This document describes the Phase 1A snapshot only and is now historical.** Every
> feature this file lists under "intentionally missing" (§11) has since been built —
> Phases 1B through 9 are complete. For current setup and deployment steps, see
> `docs/05-Deployment-Operations-Guide.md`.

# Agency OS Backend — Phase 1A (Safe Foundation)

Authentication, roles, organization structure, and audit infrastructure for the Agency OS
Workflow & Task Management System.

> **Status: Phase 1A COMPLETE — runtime verified on PostgreSQL.**
> `migrate:fresh --seed` applied all 15 migrations (including the `btree_gist` EXCLUDE
> constraint and the append-only audit trigger); `php artisan test` → **124 passed**.
> See `IMPLEMENTATION-STATUS.md` for the run record and the two defects it exposed.
>
> **Scope warning.** This is the *foundation only* (Phase 1A.1). The task workflow engine,
> assignments, reviews, notifications, chat, WhatsApp versioning and reports are
> **deliberately not implemented** — see `IMPLEMENTATION-STATUS.md`.
>
> **Business baseline:** `Agency OS_BRD_AR_v1.1_Final_Corrected.pdf` + corrected ERD v1.2 +
> the approved decision register (`APPROVED-DECISIONS-Q1-Q30.md`). Approved deviations from
> the BRD are logged in `CHANGE-REQUEST-REGISTER.md`.

---

## 1. Requirements

| Component | Version / note |
|---|---|
| PHP | **8.2+** — `composer.json` requires `^8.2` |
| Laravel | **12.x** — `laravel/framework: ^12.0` |
| PostgreSQL | **14+** (16 recommended) |
| Composer | 2.x |
| PHP extensions | `pdo_pgsql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath` |
| PostgreSQL extension | **`btree_gist`** — required by the temporary-TL `EXCLUDE` constraint; the migration issues `CREATE EXTENSION IF NOT EXISTS btree_gist`, so the database role needs that right (or pre-create it) |

**PostgreSQL is mandatory, not a preference.** The schema uses `jsonb`, partial unique
indexes, an `EXCLUDE USING gist` constraint (requires the `btree_gist` extension) and a
trigger that makes `audit_logs` append-only. MySQL/SQLite will not reproduce these
guarantees, and the test suite expects them.


## Bilingual UI foundation

The foundation screens support Arabic and English using one shared Blade layout:

- Arabic uses `dir="rtl"`; English uses `dir="ltr"`.
- The header language switch persists the choice in session and a one-year cookie.
- New installations default to Arabic through `.env.example` (`APP_LOCALE=ar`).
- Demo dashboard names, departments, tasks, statuses and priorities follow the active language.
- The primary visual identity is orange (`#F97316`) and white (`#FFFFFF`), exposed as shared CSS/Tailwind tokens.

Real user-entered entity names are not translated automatically; only known development/mock records use the locale-aware display map.

## 2. Installation

This package is a **complete Laravel application skeleton** (artisan, `public/index.php`,
`bootstrap/`, `config/`, `storage/` placeholders included). Only `vendor/` is absent,
because dependencies are never committed.

```bash
unzip agencyos-backend-phase-1a1-runtime-verified.zip -d agencyos
cd agencyos

composer install          # installs the Laravel 12 dependency set from composer.lock
cp .env.example .env
php artisan key:generate
```

**Windows users: see `WINDOWS-SETUP.md`** — PowerShell 5.1 does not support `&&`, and the
PostgreSQL client tools must be added to PATH.

### 2.1 If you copied this overlay onto an existing `laravel/laravel` skeleton

The stock skeleton ships its own migrations, which duplicate tables Agency OS defines.
Delete these three before migrating:

```
database/migrations/0001_01_01_000000_create_users_table.php
database/migrations/0001_01_01_000001_create_cache_table.php
database/migrations/0001_01_01_000002_create_jobs_table.php
```

Agency OS provides `users`, `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches` and
`failed_jobs` itself. The Agency OS migrations are also guarded with `hasTable()`, so a
leftover stock migration cannot corrupt the run — but a clean history is preferable.

### 2.2 PostgreSQL is mandatory

The application **refuses to boot on any other driver** (`DatabaseGuardServiceProvider`).
SQLite silently ignores `jsonb`, partial unique indexes, the `EXCLUDE USING gist`
constraint and the append-only trigger, which would give you a database that looks
migrated while enforcing none of the approved invariants.

## 3. PostgreSQL setup

```bash
sudo -u postgres psql <<'SQL'
CREATE ROLE agencyos LOGIN PASSWORD 'choose-a-local-password';
CREATE DATABASE agencyos      OWNER agencyos ENCODING 'UTF8';
CREATE DATABASE agencyos_test OWNER agencyos ENCODING 'UTF8';   -- used by phpunit
SQL
```

The migration creates the `btree_gist` extension itself; the database role therefore needs
`CREATE EXTENSION` rights (superuser locally, or pre-create the extension in both databases).

## 4. Environment configuration

Edit `.env` (never commit it — `.env` is git-ignored and no real password ships in this repo):

```dotenv
APP_TIMEZONE=Africa/Cairo      # required by the BRD — deadlines are 23:59 Africa/Cairo
APP_LOCALE=en                  # English fallback; Arabic added in the UI phase
DB_CONNECTION=pgsql
DB_DATABASE=agencyos
DB_USERNAME=agencyos
DB_PASSWORD=your-local-password
SESSION_DRIVER=database
```

## 5. Commands — run these in order after `composer install`

```bash
composer validate                 # manifest sanity
php -l app/Models/User.php        # or: find app database routes tests -name '*.php' -exec php -l {} \;
php artisan about                 # environment summary
php artisan config:clear && php artisan cache:clear
php artisan migrate:fresh --seed  # clean schema + roles + dev accounts
php artisan route:list            # verify the route/middleware spine
php artisan test                  # full suite (needs the agencyos_test database)
./vendor/bin/pint                 # code style (Laravel preset, pint.json)
php artisan test                  # re-run after Pint

php artisan serve                 # http://localhost:8000
curl -i http://localhost:8000/up  # health check
```

### Queue (approved decision Q28)

Email is delivered through a queue and never blocks a workflow transition. The mail module
lands in Phase 1B, but the infrastructure is configured now:

```bash
php artisan queue:work --tries=5 --backoff=60
```

## 6. Development login credentials

Created by `DemoSeeder`, which **throws if the environment is `production`**.
No production seeder can create these accounts.

| Username | Role | Password | Purpose |
|---|---|---|---|
| `admin` | Admin | `Demo1234!` | configuration + audit surface |
| `manager` | Manager | `Demo1234!` | operations |
| `tl` | Team Leader | `Demo1234!` | Marketing department leader |
| `employee` | Employee | `Demo1234!` | Marketing department member |
| `newuser` | Employee | `Demo1234!` | starts in **forced password change** |
| `disabled.user` | Employee | `Demo1234!` | deactivated — sign-in blocked |

These are development-only credentials. Real accounts are created internally by an
Admin (Managers) or a Manager (TLs and Employees) — there is **no public registration**.

## 7. How forced password change works

1. A new account is created with `must_change_password = true` (the default) and a
   temporary password issued by the Manager/Admin.
2. On sign-in, `EnsurePasswordChanged` intercepts **every** authenticated route except
   the change screen itself and `logout`, redirecting to `/password/forced`.
3. The user submits the current temporary password plus a new one (min 8 characters,
   confirmed). `AuthService::completeForcedPasswordChange()` hashes it, clears the flag,
   regenerates the session, and writes an `auth.forced_password_changed` audit entry.
4. Normal navigation resumes; the interception never fires again for that account.

Password **reset by email is not implemented** — BRD §18 assigns password reset to the
Manager/Admin, and the mail provider is an open decision (Q27). The config extension
point exists but is not routed.

## 8. Architecture overview

```
app/
  Enums/          RoleCode, UserStatus, ProjectStatus, ClientStatus, LeadershipType
                  → centralized, type-safe; no hard-coded role strings in controllers
  Models/         Role, User, Department, DepartmentLeadershipAssignment,
                  Client, Project, AuditLog
  Services/       AuthService   — login / logout / forced rotation (all audited)
                  AuditService  — the single writer for audit_logs
  Policies/       UserPolicy, DepartmentPolicy   (confirmed rules)
                  ProjectPolicy, TaskPolicy      (documented skeletons, deny-by-default)
  Http/
    Middleware/   EnsureRole, EnsureAccountActive, EnsurePasswordChanged
    Requests/     LoginRequest, ForcedPasswordRequest   (validation lives here)
    Controllers/  LoginController, ForcedPasswordController, DashboardController
                  → thin: they validate via Form Requests and delegate to services
```

Principles applied:

- **One users table, one guard, one login** for all four roles — no per-role auth systems.
- **Business logic in services**, never in controllers; controllers stay thin.
- **Authorization in policies + middleware**, enforced server-side (never by hiding buttons).
- **Confirmed invariants enforced in the database**, not only in PHP.
- **Audit is append-only** at the database level, so ordinary application code cannot edit it.

## 9. Temporary Team Leader & View Only behavior

**Appointment (Manager only).** A Manager appoints an **Employee of the same department**
as Temporary Team Leader for a dated period, with a mandatory reason — the feature exists
only to cover a primary TL's leave (Q9). Endpoints:

```
GET  /temporary-leadership                    list
POST /temporary-leadership                    appoint
POST /temporary-leadership/{assignment}/replace
POST /temporary-leadership/{assignment}/end   end early
```

All four are protected three times over: `role:manager` route middleware,
`FormRequest::authorize()`, and an explicit policy check in the controller. An Admin, Team
Leader or Employee calling them directly receives **403**.

**Role transition.** When the period starts, the employee's *effective* role becomes Team
Leader while `users.base_role_id` preserves the substantive Employee role. On expiry, early
termination or replacement, the substantive role is restored automatically. Every change
writes a `user_role_transitions` row plus an audit entry, and the operations are idempotent.

**View Only (Q14).** While a temporary leader covers a department, the primary Team Leader
keeps their assignment and can still sign in and read, but is not the *effective* leader and
performs no leadership action. This is resolved through `Department::effectiveLeader()`:

```php
$department->effectiveLeader();   // temporary leader if active, else primary
$user->leadsDepartment($id);      // true only for the EFFECTIVE leader
$user->isViewOnlyLeader();        // true for a primary TL who is currently covered
$user->canActAsLeaderOf($id);     // leads AND not view-only
```

`isViewOnlyLeader()` must never be derived from "does the user hold an active assignment" —
the primary assignment stays active throughout the leave. That was a real defect, fixed in
this pass and covered by regression tests.

## 10. Scheduled work

Temporary Team Leader periods start and end automatically (approved decision Q13), so the
Laravel scheduler must run in every environment:

```bash
# crontab
* * * * * cd /path/to/agencyos && php artisan schedule:run >> /dev/null 2>&1

# or run the transition pass manually
php artisan agencyos:process-leadership-transitions
```

The command is idempotent: running it twice changes nothing, and a missed day is caught up.

## 11. What is intentionally missing

Task workflow, assignments, first-seen tracking, reviews, transfers, hold/resume,
notifications, email delivery, chat, WhatsApp link versioning, performance scoring and
reports. Their business rules are **decided** (approved decisions Q1–Q30) — they are
deferred purely because they require the task tables, which are Phase 1B.
See `IMPLEMENTATION-STATUS.md` for the boundary and `APPROVED-DECISIONS-Q1-Q30.md` for the
rules they must implement.
