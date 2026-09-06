# Agency OS

Internal operations platform for a creative agency — cross-department task routing with
staged approvals, deadline tracking, performance scoring, daily reports and payroll.

Work arrives as a task, is routed to a department, assigned to a person, submitted for
review, approved through a multi-stage chain, and either passed to the next department or
closed. Everything that happens to it is recorded, timed, and scored.

---

## What it does

**Task workflow across departments**
A task moves through one department at a time. Each visit is a *step* with its own
assignee, dates and deadline state. Steps advance through an explicit status machine —
`waiting_assignment → in_progress → under_review → pending_manager_review → approved` —
and a step is only ever routed onward once it is truly approved.

**Multi-stage approval**
A Team Leader's approval is not the last word: every step also needs the Manager's. One
department carries an extra stage in the middle, where the Content team reviews the work
before it reaches the Manager. A Team Leader can never approve work they did themselves —
that step goes to the Manager instead.

**Deadlines and performance**
Every step carries a due date and a derived deadline state (`on_time`, `due_soon`,
`overdue`, `paused`). Putting a task on hold pauses its clock and resuming extends the due
date by exactly the paused duration, so nobody is scored for time they were blocked. A
monthly snapshot scores each employee, each Team Leader (personally and for their team),
and each department on delivery against those deadlines.

**Daily department reports**
Every active department gets a report generated each afternoon, pre-filled from that day's
real task activity. The Team Leader adds commentary and submits before midnight; late
submissions are marked as such. One department writes its report collectively — each
member types their own part and the system stacks the parts verbatim under their names.

**Payroll and spending**
Salaries, a pay window opened per person per month with dates set by hand, and the month's
bonuses and deductions folded into what each person actually takes home. Closing a month
freezes its figures, so a later raise or a new bonus can never rewrite a month already
paid. Company spending is tracked alongside, and the two totals meet at the bottom line.

**Also**: real-time chat with read receipts, an in-app and email notification system,
project and client records, temporary Team Leader cover, a full audit log, and an
assistant that can answer questions about the workspace and perform actions in it.

---

## Design decisions worth knowing

These are the constraints the codebase is built to hold, and most of the interesting code
exists to enforce one of them.

**One writer per domain.** `TaskWorkflowService` is the only thing that writes task state;
`DepartmentReportService` the only thing that writes reports; `PayrollService` the only
thing that writes money. If a status changed, exactly one method did it.

**Policies answer *who*, services answer *when*.** A policy never inspects workflow state
and a service never re-implements permission logic. `TaskStepPolicy::review()` says who may
review; `TaskWorkflowService::approve()` says what state allows it. This split is deliberate
and documented in both classes, because the two questions drift apart the moment they share
a method.

**The transition map is the authority.** `WorkflowStatus::allowedNextStatuses()` defines
every legal move, the service consults it before every write, and a unit test asserts the
map independently of the database. A transition that is not listed cannot happen.

**The database enforces what the application promises.** Check constraints on status
columns, a comment required whenever changes are requested, positive-amount constraints on
money, and an append-only audit log. Application-layer validation exists so the user gets a
readable message — not because it is the only guard.

**Money never silently recomputes.** An open pay period reads its bonuses and deductions
live; a closed one answers with figures frozen at the moment it closed.

**Roles are resolved through effective leadership, never a role column.** A Team Leader on
temporary cover holds full authority for the period and the person they cover becomes
read-only, so every authority check runs through the same resolution path.

---

## Stack

- PHP 8.2+, Laravel 12
- MySQL 8 (originally PostgreSQL; the migration to MySQL is in the commit history)
- Blade with a hand-written CSS design system, no frontend framework
- Pusher Channels for real-time chat
- PHPUnit — 950+ tests

Bilingual Arabic/English throughout, with a full right-to-left layout. Every user-facing
string lives in `lang/`, and both files are checked for parity.

---

## Getting started

```bash
composer install
cp .env.example .env
php artisan key:generate

# create the database named in .env, then:
php artisan migrate --seed

npm install && npm run build
composer run dev
```

`composer run dev` runs the web server, the queue worker and the Vite dev server together.
The queue worker matters in development — chat delivery, notifications and email all go
through it.

### Signing in

`migrate --seed` builds a demo workspace rather than an empty database, so there is
something to look at immediately. Every account uses the password **`Demo1234!`**:

| Username | Role | What it shows |
|---|---|---|
| `manager` | Manager | Everything: approvals, the monthly report, payroll and spending |
| `leila.mansour` | Team Leader (Graphic) | A department queue, submissions to review, the daily report |
| `salma.fouad` | Employee | Only their own assigned work |
| `admin` | Admin | Configuration and the audit log — deliberately no task content |

The seeded workspace holds tasks sitting in every workflow state at once — one waiting
to be assigned, one in progress, one at each of the three review gates, one sent back for
changes, one overdue and one finished — plus a month of scored performance, daily reports
at different stages, and a payroll with last month paid and frozen while this month is
still open.

It is built by driving the application's own services rather than inserting rows, so
every task carries real history and real timestamps; none of it is a state the app itself
would refuse to produce.

### Tests

```bash
php artisan test
```

Tests run against a separate MySQL database (`agencyos_test` by default) which must exist
before the first run:

```sql
CREATE DATABASE agencyos_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

---

## Layout

```
app/
  Enums/          status machines, roles, event types — the vocabulary of the domain
  Services/       all domain writes; one service owns each area
  Policies/       authorization only, no state checks
  Models/         Eloquent models and query scopes
  Listeners/      notifications fired from domain events
database/
  migrations/     schema plus the check constraints that guard it
docs/             requirements, ERD, technical plan and operations guide
lang/{ar,en}/     every user-facing string, in both languages
tests/            79 feature suites, 6 unit suites
```

`docs/` holds the requirements this was built against, the entity model, and the decision
register — including the questions raised during the build and the answers that settled
them. Several of those answers are quoted directly in the code where they are enforced.

---

## Notes

This is a working system extracted from a private repository for public reference. The
client's branding, credentials and production configuration have been removed; the domain
logic, schema and tests are unchanged.
