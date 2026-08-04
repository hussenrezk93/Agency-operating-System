# Changelog

**v1.1-demo (25 Jul 2026) — Complete Frontend Demo**
- Added demo session + login states, route guards, 403/404/500/session-expired
- New engine: centralized i18n (EN/AR), LocalStorage store + workflow engine, guards, rebuilt shell
- Tasks / assign / execute / review / create / details / history rebuilt fully dynamic (whole lifecycle clickable, incl. hold/resume/redirect/skip/cancel/deadline with mandatory reasons)
- Users, departments, temporary TLs, clients, projects rebuilt with working CRUD modals + audit trail
- Project details: tabs incl. WhatsApp link versioning + invite-delivery ledger with resend
- Employees see the projects their department participates in (nav item, scoped list, membership-guarded details, Join-group button); project creation sends membership notifications + WhatsApp invitations to participating departments
- Notifications (tabs, mark read, email badges), chat (send/delete-for-all), audit log (filters, details, CSV)
- Profile + email-verification flows, forced/normal password change
- Reports: Demo Data label, real CSV export + print; performance pages. **Role-scoped visibility** — Manager sees all, TL sees only their department/employees/own score, Employee sees only their own report
- Global: no dead buttons (simulated-action toasts), role+lang preserved everywhere, RTL/LTR complete, responsive fixes, Demo Role Switcher with reset/expiry

**v1.1.2-demo (25 Jul 2026) — visual polish pass**
- Fixed a layout bug: `.grid-2/.grid-3/.grid-4` were missing `display:grid`, collapsing two/three-column layouts (projects, task details, departments, profile, performance) into stacked full-width rows — now proper responsive grids
- Project cards redesigned: gradient top accent (both brand oranges), client avatar bubble, department route, gradient progress bar, WhatsApp footer pill, hover lift; link-underline artifacts removed
- Top-right profile menu redesigned: gradient orange header (avatar, name, role chip), icon-bubble items with hover states, email-verification hint under Profile, red sign-out — icon-size bug fixed
- My Performance rebuilt: gradient hero score card with conic progress ring + colored due/late stat cards

**v1.1.1-demo (25 Jul 2026) — UI/UX refinement pass**
- Design-system form layer: consistent inputs/selects/textareas with focus + validation states everywhere (form-grid, form-section, chip-check, choice-toggle, nested-panel, rep-item, form-actions-sticky, modal lg/xl variants, hint + seen-badge styles)
- Project creation rebuilt as a 5-section form card (Basic info · Client · Departments · Reference links · WhatsApp) with the auto number as an info row, Existing/New client segmented toggle, polished nested new-client panel (name*, phone*, **email optional** — stored with web:"", desc:"", status:"active"), department chip-cards, repeatable reference-link rows, sticky footer
- New client + New department modals restyled (subtitles, helper notes, required markers, balanced grids)
- **Automatic Seen**: opening My Tasks as the employee marks all currently assigned unseen tasks as seen — no button; first-seen timestamp written once (reload-safe), audited once, TL notified; TL/Manager see 👁 Seen / ◌ Not seen yet badges on the task list and task details (hidden from employees)

**v1.0 (24 Jul 2026)** — static 20-screen UI/UX prototype (approved design baseline)

## Arabic UI and dual-primary update
- Added a persistent two-sided language switch with EN on the left and العربية on the right.
- Arabic mode now applies RTL to the whole interface and localizes visible hardcoded copy and mock data.
- English mode continues to show the canonical English demo data.
- Declared Orange and White as the two primary brand colors in the shared design tokens.
