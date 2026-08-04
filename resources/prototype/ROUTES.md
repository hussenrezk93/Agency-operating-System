# Agency OS Demo — Route Map

**Query parameters (preserved automatically on every internal link):**

- `?lang=en|ar` — interface language + direction (default `en`)
- `?role=admin|manager|tl|employee` — active demo role; if it differs from the session role the
  session switches to it (Demo Role Switcher behaviour, prototype only)
- `?id=…` — record id where noted
- `?view=my` (tasks) · `?mode=managers` (users) · `?q=…` (tasks search) · `?next=…` (login return)

**Guard behaviour (assets/guards.js):**

1. No session on a protected page → redirect `login.html?next=<page>` (target restored after login).
2. Session flagged *must change password* → redirect `forced-password-change.html`.
3. Role not in the page's allow-list → redirect `403.html`.
4. Public pages skip all checks.

| Route | Allowed roles | Params | Notes |
|---|---|---|---|
| index.html | public | — | demo cover + credentials |
| login.html | public | lang, next | states: invalid, disabled, loading, show/hide, forced-change (`newuser`) |
| 403.html / 404.html / 500.html | public | — | error pages |
| session-expired.html | public | — | reached via "Simulate session expiry" |
| email-verification-required / -success / -expired .html | public | — | success marks current user verified + audits |
| forced-password-change.html | any session | — | clears the must-change flag |
| change-password.html | any session | — | validation: ≥8 chars + match |
| profile.html | any session | — | change email → verification resets, audited |
| admin-dashboard.html | admin | — | static KPI snapshot |
| managers.html | admin | — | **redirects** → users.html?mode=managers |
| users.html | admin, manager | mode=managers | Admin=Managers only · Manager=TL+Employee. Create/edit/disable/reset-pw/resend-verify = **modals**; filters, search, pagination |
| user-create.html / user-edit.html | admin, manager | — | **aliases** → users.html (modal flows) |
| routing-permissions.html | admin | — | clickable ✓/✗ matrix, audited |
| output-access-permissions.html | admin | — | rules table + create modal + scope toggle |
| system-settings.html | admin | — | SPF/DKIM/DMARC, digest interval (saved) |
| audit-log.html | admin | — | filters, details modal (before/after/IP), CSV export |
| manager-dashboard.html | manager | — | static KPI snapshot |
| departments.html | admin, manager | — | live cards; create/edit = **modals**; disable warns on active tasks |
| department-create/edit.html | admin, manager | — | **aliases** → departments.html |
| temporary-tl.html | manager | — | list + end-early; new delegation = **modal** with overlap check |
| temporary-tl-create.html | manager | — | **alias** → temporary-tl.html |
| clients.html | manager, tl | — | live table; create/edit = **modals**; phone validation |
| client-create/edit.html | manager, tl | — | **aliases** → clients.html |
| client-details.html | manager, tl | id | client info + projects |
| projects.html | manager, tl, **employee** | — | live cards, status filter, WA indicator · **Employee sees only projects their department participates in** (no create) |
| project-create.html | manager, tl | — | client select / inline new client, departments, refs, optional validated WA link |
| project-details.html | manager, tl, employee | id | tabs: Overview · Tasks · 💬 WhatsApp · Invite deliveries. Complete / cancel-with-reason (Manager). Closed = read-only. **Employees**: membership-guarded (403 outside their department's projects), Join-group button, link managed only by Manager/creator, deliveries tab hidden |
| project-edit.html | manager, tl | — | **alias** → projects.html (inline actions on details) |
| project-whatsapp-history.html | — | id | **alias** → project-details.html (WhatsApp tab) |
| project-invite-deliveries.html | — | id | **alias** → project-details.html (Deliveries tab) |
| tl-dashboard.html | tl | — | static KPI snapshot |
| employee-dashboard.html | employee | — | static KPI snapshot |
| tasks.html | manager, tl, employee | view=my, q | role-scoped list: Manager=all · TL=department (+My Tasks) · Employee=assigned; live status/priority/deadline filters + search |
| task-create.html | manager, tl | — | auto number, validation, first department, refs, draft |
| task-details.html | manager, tl, employee | id | route rail, timeline, outputs, first-seen (TL/Manager only), action modals: reassign / deadline / hold / resume / redirect / skip / cancel (reasons required) |
| task-history.html | manager, tl, employee | id | full timeline page |
| task-assign.html | tl | id | employee radio-cards with workload, self-assign (Manager reviews), dates, 23:59 note |
| task-execute.html | employee, tl | id | assignee only (others → details); records first view; outputs add/remove; submit blocked without output |
| tl-review.html | tl, manager | id (optional) | queue + review; TL never reviews own self-assigned step (Manager does); approve → send-next (routing-limited) or finish |
| notifications.html | any session | — | tabs, mark one/all, ✉ sent/queued/failed badges, unverified-email warning |
| chat.html | any session | — | membership-based conversations; send text/link; delete-for-all → placeholder + audited event (no content); Admin sees a no-content notice |
| reports.html | manager, tl, employee | — | **Role-scoped**: Manager = all departments/TLs/employees · TL = own department + own employees + own score only · Employee = redirected to their own report (employee-performance.html). Demo Data label, CSV export + print |
| employee-performance.html | manager, tl, employee | — | personal score (N/A when no due steps) |
| department-performance.html | manager, tl | — | compliance table + CSV export · TL sees **only their own department** |
