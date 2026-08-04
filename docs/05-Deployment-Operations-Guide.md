# 05 — Deployment & Operations Guide

Phase 10 deliverable. Reflects the application as it stands today (Phases 0–9 complete,
plus the chat company-directory extension and the Phase 10 hardening pass) — not the
Phase 1A snapshot in `README-BACKEND.md`, which is now historical only.

---

## 1. Requirements

| Component | Version |
|---|---|
| PHP | 8.2+ |
| Laravel | 12.x |
| PostgreSQL | 14+ (16 recommended) — **mandatory**, not a preference (see below) |
| Composer | 2.x |
| PHP extensions | `pdo_pgsql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath` |
| PostgreSQL extension | `btree_gist` (the temporary-TL `EXCLUDE` constraint needs it; the migration creates it itself, so the DB role needs `CREATE EXTENSION` rights, or pre-create it) |

The application relies on PostgreSQL-specific features throughout: `jsonb` columns,
partial unique indexes, `EXCLUDE USING gist`, and CHECK constraints on nearly every table
(chat conversation types, task workflow transitions, review decisions, digest windows,
etc.) — many of the business invariants this app enforces live in the schema, not just in
PHP. Deploying on any other database engine would silently drop that protection.

## 2. First-time setup

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Create the database and role:

```bash
sudo -u postgres psql <<'SQL'
CREATE ROLE agencyos LOGIN PASSWORD 'choose-a-strong-password';
CREATE DATABASE agencyos OWNER agencyos ENCODING 'UTF8';
SQL
```

Edit `.env` — see §3 for what needs real values before this is production-safe. Then:

```bash
php artisan migrate --force      # never migrate:fresh against a database with real data
php artisan db:seed --class=RoleSeeder --force   # roles only — DemoSeeder refuses to run
                                                    # when APP_ENV=production
php artisan route:list           # sanity check
php artisan test                 # full suite, against a SEPARATE test database (see phpunit.xml)
./vendor/bin/pint                # code style
```

The very first Admin account has to be created directly in the database (there is no
public registration — BRD §18 — and no seeder is allowed to create one in production):

```bash
php artisan tinker
>>> $role = \App\Models\Role::where('code', 'admin')->firstOrFail();
>>> \App\Models\User::create([
...   'full_name' => 'Your Name', 'username' => 'admin',
...   'role_id' => $role->id, 'personal_email' => 'you@company.com',
...   'password_hash' => \Illuminate\Support\Facades\Hash::make('a-temporary-password'),
...   'status' => 'active', 'must_change_password' => true,
...   'email_verified_at' => now(),
... ]);
```

## 3. `.env` values that must be real before go-live

| Variable | Note |
|---|---|
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` — leaving this `true` leaks stack traces (including query bindings) to any visitor who triggers an error |
| `APP_URL` | the real public URL |
| `APP_TIMEZONE` | `Africa/Cairo` — every deadline in the system is BRD-defined as 23:59 Africa/Cairo; changing this changes every SLA calculation |
| `DB_*` | production database credentials |
| `SESSION_SECURE_COOKIE` | `true` once served over HTTPS |
| `MAIL_*` | real SMTP credentials (approved decision Q27 — provider-agnostic; every notification email, the 2-hour chat digest, WhatsApp invite emails, and forced-password-reset hand-offs depend on this) |
| `QUEUE_CONNECTION` | `database` is fine at this scale; move to Redis only if queue depth becomes a real bottleneck |

### Mail domain authentication (SPF / DKIM / DMARC)

Not configurable from `.env` — this lives in DNS for whatever domain `MAIL_FROM_ADDRESS`
uses, and it's the single biggest factor in whether notification/digest/WhatsApp-invite
emails land in an inbox instead of spam:

- **SPF**: a TXT record on the sending domain authorizing your SMTP provider's servers
  (e.g. `v=spf1 include:<provider>.com ~all`). Ask your SMTP provider for their exact
  `include` value.
- **DKIM**: the SMTP provider generates a key pair and gives you a CNAME/TXT record to
  publish; they sign outgoing mail with the private half.
- **DMARC**: a TXT record (`_dmarc.<domain>`) stating what to do with mail that fails
  SPF/DKIM — start with `p=none` (monitor only) and tighten to `p=quarantine`/`p=reject`
  once SPF/DKIM are confirmed passing in your provider's delivery logs.

Verify all three with a real test send before go-live (most SMTP providers' dashboards
show pass/fail per message). This was flagged as an unchecked item in the Phase 0 plan
and is genuinely an infrastructure/DNS task, not application code — nothing in this
repository can do it for you.

## 4. Long-running processes

Two things must run continuously in every environment, or the application silently stops
doing time-driven work (deadlines don't reclassify, digests don't send, temporary
leadership periods don't start/end) while still looking fully functional to anyone
clicking around:

**Queue worker** — approved decision Q28: email never blocks a request.
```bash
php artisan queue:work --tries=5 --backoff=60
```
Run this under a process supervisor (systemd, Supervisor, etc.) that restarts it on
crash and on deploy. `QUEUE_FAILED_DRIVER=database-uuids` is already set — check
`failed_jobs` periodically; `agencyos:notification-retry-sweep` (below) only retries the
two ledgers that track their own attempts (chat digests, WhatsApp invite emails), not
arbitrary failed jobs.

**Scheduler** — one cron line, Laravel dispatches everything else from `routes/console.php`:
```bash
* * * * * cd /path/to/agencyos && php artisan schedule:run >> /dev/null 2>&1
```

Everything currently scheduled (all `withoutOverlapping()`, all safe to miss-and-catch-up):

| Command | Cadence | Purpose |
|---|---|---|
| `agencyos:process-leadership-transitions` | daily 00:05 | starts/ends temporary TL periods (Q13) |
| `agencyos:deadlines-due-soon` / `agencyos:deadlines-overdue` | every 15 min | `deadline_status` reclassification |
| `agencyos:notification-digest-sweep` | every 3 hrs | batches general notification emails |
| `agencyos:chat-digests` | every 15 min | closes due 2-hour chat digest windows (BRD §14) |
| `agencyos:notification-retry-sweep` | every 10 min | retries failed WhatsApp/chat-digest sends |
| `agencyos:notification-failure-alert` | daily 07:00 | alerts Managers (in-app) about repeated failures |
| `agencyos:performance-snapshot` | monthly, 1st at 00:30 | final monthly performance scores (BRD §17) |
| `agencyos:performance-refresh` | daily 01:00 | provisional in-progress-month scores for dashboards |

If the scheduler is down for a stretch, every one of these catches up cleanly on the next
run — none of them assume they ran on the previous tick.

## 5. Backup

Nothing is stored outside PostgreSQL — there is no local file-upload feature (task/output
links are external URLs the user pastes in, not uploaded files), so a database backup is a
complete backup.

```bash
pg_dump -Fc -U agencyos agencyos > agencyos_$(date +%Y%m%d_%H%M).dump
```

Restore:
```bash
pg_restore -U agencyos -d agencyos --clean agencyos_20260101_0000.dump
```

Back up on a schedule appropriate to how much rework losing a day would cost (daily, at
minimum), and verify the dump restores cleanly somewhere other than production at least
once — an untested backup is not a backup.

`audit_logs` is append-only at the database level (a trigger blocks `UPDATE`/`DELETE`), so
it will grow forever; there is no built-in retention/archival job. Decide a retention
policy before it becomes a real storage line item — this was not scoped in the BRD.

## 6. Health check & monitoring

`GET /up` (Laravel's default health-check route) returns 200 when the app has booted and
can reach the database. Point uptime monitoring at it.

Application logs: `storage/logs/laravel.log` (`LOG_CHANNEL=stack`). Nothing sensitive is
ever logged deliberately — `AuditService` explicitly never receives passwords, hashes, or
chat message content — but review log retention/access anyway before go-live.

## 7. Rolling back a bad deploy

This repository now has git history (initialized during the Phase 10 hardening pass — see
`git log`). A bad code deploy can be reverted with the normal `git revert`/redeploy flow.
A bad *migration* is a different story: only revert a migration with `php artisan
migrate:rollback` if you are certain no data written under the new schema needs to
survive — several migrations in this app add CHECK constraints and NOT NULL columns that
are not safely reversible once real rows exist under them. When in doubt, roll forward
with a fix instead of rolling the schema back.

## 8. What Phase 10 did NOT cover

- Infrastructure choice (server, containers, CDN, TLS termination) — not part of this
  application's scope; deploy it the way your organization deploys any Laravel app.
- Load/performance testing at scale.
- A formal WCAG accessibility audit (the UI follows sensible contrast/focus-state
  practices throughout, but this was not independently verified against WCAG criteria).
