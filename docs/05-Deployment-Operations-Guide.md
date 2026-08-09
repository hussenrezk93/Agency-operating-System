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
| MySQL | 8.0.16+ — **mandatory**, not a preference (see below); MariaDB is not a supported substitute |
| Composer | 2.x |
| PHP extensions | `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath` |

The schema relies on MySQL 8-specific features throughout: native `CHECK` constraint
support (8.0.16+), generated/virtual columns standing in for what would be partial unique
indexes on other engines, and two `SIGNAL SQLSTATE`-based trigger pairs enforcing
`audit_logs` append-only and blocking an output from superseding itself — many of the
business invariants this app enforces live in the schema, not just in PHP. The one rule
that has no MySQL-native equivalent at all — no two active temporary Team Leader periods
may overlap on the same department — is enforced in
`TemporaryLeadershipService::assertNoTemporaryOverlap()` instead, under a row lock; see
that method's doc comment for why the schema alone can't guarantee it here. Deploying on
any other database engine, or MariaDB, is unverified and may silently drop some of this
protection (MariaDB in particular does not enforce `CHECK` constraints on some older
default configurations).

## 2. First-time setup

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Create the database and user:

```bash
mysql -u root -p <<'SQL'
CREATE DATABASE agencyos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'agencyos'@'localhost' IDENTIFIED BY 'choose-a-strong-password';
GRANT ALL PRIVILEGES ON agencyos.* TO 'agencyos'@'localhost';
FLUSH PRIVILEGES;
SQL
```

On Hostinger shared/business hosting (cPanel), create the database, user, and grant
through cPanel's "MySQL Databases" tool instead — it enforces `utf8mb4` by default and
there's no shell access to run the above directly.

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
| `BROADCAST_CONNECTION`, `PUSHER_*` | real Pusher Channels app credentials — chat (BRD §14) pushes new messages/deletions live over this. See below. |

### Chat real-time (Pusher)

Chat's live updates (`app/Events/ChatMessageBroadcast.php`, `ChatMessageDeletedBroadcast.php`)
go over [Pusher Channels](https://pusher.com) instead of the browser polling on a timer.
Free tier is enough for this app's scale (200k messages/day, 100 concurrent connections).

1. Create an app at pusher.com (Channels product, not Beams/Chatkit) and copy its
   `app_id` / `key` / `secret` / `cluster` into `.env`.
2. **The queue worker (§4) must actually be running** — `ChatMessageBroadcast`/
   `ChatMessageDeletedBroadcast` both implement `ShouldBroadcast`, which queues a job
   rather than calling Pusher's API inline (so a chat send/delete request is never slowed
   down by an external HTTP call). If the worker is down, messages still save correctly
   but nobody sees them live until it's back up and drains the backlog.
3. Nothing else to configure — `routes/channels.php` authorizes each private channel
   subscription through the exact same `ChatPolicy::view()` check the HTTP routes use, and
   `bootstrap/app.php`'s `withBroadcasting()` call registers `/broadcasting/auth`
   automatically.
4. If `PUSHER_APP_KEY` is left blank (e.g. mid-setup), the chat page still works — it just
   falls back to a single one-time sync on page load instead of live updates, per the
   guard in `resources/views/chat/index.blade.php`'s script block.

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
clicking around. **Hostinger shared/business hosting cannot run a persistent process at
all** (no SSH, no systemd/Supervisor access) — both the queue worker and the scheduler
have to run as short-lived cron ticks instead, set up through cPanel's "Cron Jobs" tool,
not a crontab file edited by hand.

**Queue worker** — approved decision Q28: email never blocks a request.

- **VPS / any host with process-supervisor access:**
  ```bash
  php artisan queue:work --tries=5 --backoff=60
  ```
  Run this under a process supervisor (systemd, Supervisor, etc.) that restarts it on
  crash and on deploy.

- **Hostinger shared hosting (no persistent processes) — cPanel Cron Jobs, every minute:**
  ```bash
  cd /home/USERNAME/agencyos && php artisan queue:work --stop-when-empty --tries=5 --backoff=60 >> /dev/null 2>&1
  ```
  `--stop-when-empty` is what makes this safe to run from cron: the worker drains
  whatever is queued and exits on its own, rather than staying resident — so a plain
  every-minute cron tick approximates a real worker (up to ~1 minute of added latency
  on queued email/broadcast jobs, never more) without needing a daemon at all. Two
  ticks can safely overlap (a job already being processed isn't picked up twice), so
  there's no need for `withoutOverlapping()`-style locking here.

Either way, `QUEUE_FAILED_DRIVER=database-uuids` is already set — check `failed_jobs`
periodically; `agencyos:notification-retry-sweep` (below) only retries the two ledgers
that track their own attempts (chat digests, WhatsApp invite emails), not arbitrary
failed jobs.

**Scheduler** — one cron line, Laravel dispatches everything else from `routes/console.php`:
```bash
* * * * * cd /path/to/agencyos && php artisan schedule:run >> /dev/null 2>&1
```
On Hostinger, add this exact line as a cPanel Cron Job ("Every Minute") pointing at your
account's actual path (cPanel shows it, typically `/home/USERNAME/agencyos` or under
`public_html`) — cPanel's cron IS a real, standard cron, so this line works unchanged;
only the queue worker needed a different invocation above, not the scheduler itself.

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

Nothing is stored outside MySQL — there is no local file-upload feature (task/output
links are external URLs the user pastes in, not uploaded files), so a database backup is a
complete backup.

```bash
mysqldump -u agencyos -p --single-transaction --routines --triggers agencyos > agencyos_$(date +%Y%m%d_%H%M).sql
```

`--routines --triggers` matters here specifically — a dump without them silently drops the
`audit_logs_no_update`/`audit_logs_no_delete` and `tso_no_self_supersede_ins`/`_upd`
triggers, so a restored database would look fine until one of those invariants is the thing
that was supposed to catch a bug.

Restore:
```bash
mysql -u agencyos -p agencyos < agencyos_20260101_0000.sql
```

On Hostinger shared hosting, use cPanel's phpMyAdmin export/import (Export tab → check
"Structure" with triggers included) instead — the above assumes shell access, which shared
hosting doesn't provide.

Back up on a schedule appropriate to how much rework losing a day would cost (daily, at
minimum), and verify the dump restores cleanly somewhere other than production at least
once — an untested backup is not a backup.

`audit_logs` is append-only at the database level (a pair of triggers blocks `UPDATE` and
`DELETE`), so it will grow forever; there is no built-in retention/archival job. Decide a
retention policy before it becomes a real storage line item — this was not scoped in the
BRD.

## 6. Health check & monitoring

`GET /up` (Laravel's default health-check route) returns 200 when the app has booted and
can reach the database. Point uptime monitoring at it.

Application logs: `storage/logs/laravel.log` (`LOG_CHANNEL=stack`). Nothing sensitive is
ever logged deliberately — `AuditService` explicitly never receives passwords, hashes, or
chat message content — but review log retention/access anyway before go-live.

## 7. Production performance checklist

The application-level optimization pass (eager-loading N+1 fixes, missing indexes,
static-asset cache-busting + headers) is committed to the repo and needs nothing extra
to take effect. These four, by contrast, are **deploy-time steps** — they cache
environment-specific state to disk, so they must run on every deploy, not once:

```bash
composer install --no-dev --optimize-autoloader   # classmap instead of PSR-4 filesystem lookups
php artisan config:cache                          # freezes .env reads into one file
php artisan route:cache                           # skips re-registering every route on each request
php artisan view:cache                             # precompiles every Blade template
```

**Why these are safe here specifically** (verified during the optimization pass, not
assumed): no controller, service, or view calls `env()` directly outside `config/*.php`
(the one thing that silently breaks after `config:cache`), and every route in
`routes/web.php` — including its handful of closures — cached and served correctly under
`route:cache`. Re-run all four after every deploy that changes `.env`, routes, config, or
Blade files; a stale cache serves the OLD version of whichever one you skipped.

**Never run `config:cache`/`route:cache`/`view:cache` in local development or before
running the test suite** — `phpunit.xml`'s environment overrides (test database,
`SESSION_DRIVER`, etc.) are only honored when config is read live. This is not
theoretical: enabling them during this optimization pass caused an immediate,
reproducible CSRF/session test failure until cleared. `php artisan optimize:clear` undoes
all four at once.

Also confirm before go-live:
- **OPcache** is enabled in `php.ini` (`opcache.enable=1`, `opcache.validate_timestamps=0`
  in production — the last one means a deploy MUST restart PHP-FPM/the app server to pick
  up new code, since OPcache stops checking file mtimes).
- The queue worker and scheduler (§4) are both actually running — several of this app's
  performance-relevant behaviors (the notification digest sweep, the deadline
  reclassification pass) are background jobs, not request-time work, and do nothing if
  the worker is down.

## 8. Rolling back a bad deploy

This repository now has git history (initialized during the Phase 10 hardening pass — see
`git log`). A bad code deploy can be reverted with the normal `git revert`/redeploy flow.
A bad *migration* is a different story: only revert a migration with `php artisan
migrate:rollback` if you are certain no data written under the new schema needs to
survive — several migrations in this app add CHECK constraints and NOT NULL columns that
are not safely reversible once real rows exist under them. When in doubt, roll forward
with a fix instead of rolling the schema back.

## 9. What Phase 10 did NOT cover

- Infrastructure choice (server, containers, CDN, TLS termination) — not part of this
  application's scope; deploy it the way your organization deploys any Laravel app.
- Load/performance testing at scale. The optimization pass eliminated known N+1 queries
  and added missing indexes and verified them with query-count regression tests
  (`tests/Feature/PerformanceRegressionTest.php`), but that is code-level correctness, not
  a substitute for load-testing under real concurrent traffic.
- A formal WCAG accessibility audit (the UI follows sensible contrast/focus-state
  practices throughout, but this was not independently verified against WCAG criteria).
