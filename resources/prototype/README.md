# Agency OS — Complete Frontend Demo

**Workflow & Task Management System · Interactive frontend-only demo · BRD v1.1 / ERD v1.2**

This is a **frontend management demo**. There is **no backend**: no PHP, no APIs, no database.
Everything runs in the browser with realistic demo data persisted in **LocalStorage**, so your
changes (created tasks, approvals, new clients, WhatsApp link versions…) survive page reloads
and can be reset at any time.

---

## 1 · How to run

1. Unzip the package.
2. Open **`index.html`** directly in Chrome, Edge, Firefox, or Safari (double-click — no server, no npm).
3. Click through to the **login page** and sign in with a demo account below.

> All scripts are plain `<script>` files (no ES modules), so everything works under `file://`.

## 2 · Demo credentials

| Role | Username | Password | Lands on |
|---|---|---|---|
| Admin | `admin` | `Demo123!` | admin-dashboard.html |
| Manager | `manager` | `Demo123!` | manager-dashboard.html |
| Team Leader (Marketing) | `tl` | `Demo123!` | tl-dashboard.html |
| Employee (Marketing) | `employee` | `Demo123!` | employee-dashboard.html |

Extra login states for the demo:

- `disabled` + any password → **"account disabled"** error state
- `newuser` + `Demo123!` → **forced password change** flow before entering the system
- wrong password → invalid-credentials state · empty fields → validation state

**Demo Role Switcher (black panel, bottom corner of every screen)** lets you jump between the
four roles, jump to any screen, **Reset demo data**, simulate **session expiry**, and log out.
It is labelled *Prototype Only* and would not exist in production.

## 3 · Purpose of each role

- **Admin** — configuration only: Managers' accounts, routing permissions between departments,
  output-visibility rules, system settings, and the **Audit Log** (Admin-only). Admin never sees
  task content or chat content.
- **Manager** — full operations: users, departments, temporary TLs, clients, projects, all tasks,
  reports. Reviews **self-assigned TL steps** (a TL never approves their own work). Can hold /
  resume / redirect / skip / cancel with mandatory reasons.
- **Team Leader** — one department: receives tasks (Waiting Assignment), assigns one employee
  *or self-assigns*, sets start/due dates (deadline is always 23:59 Africa/Cairo), reviews
  submissions, requests changes with a mandatory comment, sends approved work to a
  routing-allowed department, or finishes the task.
- **Employee** — executes: sees the brief and previous departments' outputs, adds output links
  (≥1 required to submit), comments, submits for review. Their **first view is auto-recorded**
  and visible to TL/Manager only.

## 4 · Recommended management walkthrough (≈10 min)

1. **Login as `manager`** → Manager Dashboard.
2. Open **Clients → ＋ New client** — create one (phone is validated).
3. Open **Projects → ＋ New project** — pick the client (or create one inline)…
4. …tick the **participating departments**…
5. …add a reference link…
6. …and paste a **WhatsApp invite link** (`https://chat.whatsapp.com/…` — validation included). Create.
7. **Tasks → ＋ New task** — note the auto number; choose **Marketing** as first department. Create.
8. Switch role to **Team Leader** (black panel) → the task is in **Waiting Assignment** → open
   **Assign**, pick *Mohamed Ali* (workload shown) or **self-assign**, set dates → Confirm.
9. Switch to **Employee** → *My Tasks* → open the task. **First view is recorded** the moment it opens.
10. Add an output link, then **Submit for review** (try submitting with no output — it's blocked).
11. Back as **TL** → **Review Queue** → open it → **Request changes** (the comment is mandatory).
12. As **Employee** again → the amber changes banner shows the comment → fix, **Resubmit**.
13. As **TL** → **Approve**.
14. **Send to next department** — only routing-allowed departments appear (Admin's matrix).
15. Or **Finish Task** — it becomes read-only forever (try any action: everything is locked).
16. Open **Notifications** — every action you just did produced entries with ✉ email badges;
    mark one / mark all as read.
17. Open **Chat** — send a message in *Marketing — team*, then delete it: content disappears for
    everyone, and only the **event** is audited.
18. Open **Reports** — Department / TL / Employee reports with the score formula, N/A handling,
    a live department filter, real CSV export and print.
19. Switch to **Admin** (black panel).
20. Open **Audit Log** — filter, open a row's **details (before → after)**, export CSV. Also visit
    **Routing Permissions** and click a ✓/✗ cell — the change is saved and audited.

Bonus flows: `manager → Temporary TLs` (delegate Photography, overlap prevented) ·
`project-details → 💬 WhatsApp tab` (replace the link → **v3**, invitation re-sent, delivery
ledger updates) · `profile` (change email → verification resets → simulate the email link).

## 5 · Route map & permissions

See **ROUTES.md** for every route, allowed roles, query parameters, and redirect behaviour.
Small flows from the spec are implemented as **modals or tabs** (documented there); their
`.html` aliases still exist and redirect to the host page, so no route 404s.

## 6 · Frontend-only limitations (honest list)

- **No backend.** All permissions are enforced by the frontend guard for demo purposes; the real
  enforcement will live in Laravel (Phase 1+). LocalStorage is the "database".
- The four **dashboards** are rich static snapshots — their KPI numbers illustrate the design and
  don't recalculate from the store (all list/detail pages *are* live).
- **Emails, WhatsApp and verification links are simulated**: statuses (sent / queued / failed),
  digests and the 24-hour verification link are modelled as data + flows, nothing is actually sent.
  The system stores/distributes a WhatsApp *invite link* only — never the WhatsApp API.
- Deep report drill-downs and some illustrative sentences represent **stored content** and remain
  in English by design (BRD rule: entity names/content stored in English); all **UI chrome,
  buttons, statuses, toasts, dialogs and validation messages** are fully translated EN/AR with
  RTL/LTR.
- Decorative controls that have no meaning without a backend show a clear **"Simulated in this
  demo"** toast instead of doing nothing — no dead buttons.
- "Today" is pinned to **25 Jul 2026** so deadline states (overdue / near deadline) stay stable
  for presentations.

## 7 · Files

```
index.html            ← start here
login.html …          51 screens (see ROUTES.md)
assets/agencyos.css    design tokens + components (orange #F97316 on white)
assets/i18n.js        centralized EN/AR dictionary
assets/guards.js      demo session + route guards
assets/mock-data.js   seed data (users, departments, tasks, projects…)
assets/demo-store.js  LocalStorage store + workflow engine
assets/agencyos.js     shell, navigation, components, global behaviours
```

*Agency OS internal · Confidential · Africa/Cairo · v1.1 demo*

## Arabic / English update
- The language selector is now a two-sided switch: **EN on the left** and **العربية on the right**.
- Arabic mode applies RTL to the entire interface and localizes visible demo/mock data, names, departments, projects, tasks, notifications, comments, and reports.
- English mode keeps the canonical English demo data.
- The selected language is saved in LocalStorage.
- Brand primary colors are explicitly **Orange + White**.
