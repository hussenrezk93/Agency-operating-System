# Agency OS Demo — QA Checklist

## Automated checks (run before packaging)
- [x] `node --check` on all 5 engine files (i18n, guards, mock-data, demo-store, agencyos) — clean
- [x] `node --check` on every page's embedded `<script>` block (19 dynamic pages) — clean
- [x] Link audit: every internal `href` target (HTML + JS-generated) exists — 0 missing
- [x] i18n audit: every `data-i18n` key used in pages exists in the dictionary — 0 missing
- [x] No `TODO` / placeholder strings in delivered files

## Roles & guards
- [x] All 4 accounts log in → land on the correct dashboard
- [x] `disabled` account → disabled error · wrong password → invalid error · empty → required error
- [x] `newuser` → forced-password-change before any page
- [x] No session + protected page → login (with `next=` return)
- [x] Employee opening audit-log/users/routing → 403 · Admin opening chat → no-content notice
- [x] Reports scoping: Manager = everything · TL = own department + own employees + own score only · Employee = own report only (auto-redirect)
- [x] Sidebar renders per role exactly as spec §6; guards enforced server-side-style in guards.js (not just hidden buttons)
- [x] `role` + `lang` (+ `id`) preserved on every internal link (global rewriter + `withP`)

## Workflow (end-to-end, persisted in LocalStorage)
- [x] Create task → Waiting Assignment at first department's TL
- [x] Assign (workload shown) / self-assign (Manager-review warning) / dates validated
- [x] First view auto-recorded on employee open of task-execute AND bulk on My Tasks page load (employee only, assigned+unseen+active only); reload-safe (firstSeen never overwritten, single audit entry); TL/Manager see Seen/Not-seen badges; hidden from employees
- [x] New-client email in project form: optional, validated only when filled, stored with web:""/desc:""/status:active
- [x] Submit blocked with 0 outputs; outputs removable before submit only
- [x] Request changes requires comment → returns to same employee with banner
- [x] Approve → send-next limited to Admin routing matrix → new Waiting Assignment step
- [x] Finish → Completed → read-only (no actions render); Cancel/Redirect/Skip/Hold require reasons
- [x] Hold pauses deadline status; Resume extends due date; deadline change notifies assignee
- [x] Every action writes timeline + audit + notification entries

## CRUD
- [x] Users: create/edit/disable/reactivate/reset-pw/resend-verify, filters, search, pagination, uniqueness
- [x] Departments: unique name, TL required, disable warns on active tasks
- [x] Temporary TL: overlap in same department blocked; end-early works
- [x] Clients: phone/email validation, disable, projects drill-down
- [x] Projects: create (inline new client), complete, cancel-with-reason, closed = read-only + no new tasks
- [x] Employee project visibility: list + details limited to own department's projects; Join-WhatsApp button; no link management; deliveries tab hidden
- [x] Project creation notifies members of every participating department (+ WhatsApp invitation notification & delivery ledger when a link is attached)
- [x] WhatsApp: URL validated (chat.whatsapp.com), replace → new version + resend + delivery ledger, manual-removal warning, read-only when closed
- [x] Notifications: mark one/all, tab counts, ✉ badges; Chat: send text/link, delete-for-all placeholder + audited event without content
- [x] Audit: filters, before/after modal, CSV export downloads a real file
- [x] Reset Demo Data restores the seed

## Language & responsive
- [x] EN (LTR) and AR (RTL) verified on: login, shell/nav, tasks, task-details, modals, toasts, chat, notifications
- [x] Widths 1440 / 1024 / 768 / 390 / 360: collapsible sidebar + scrim, tables scroll in `.table-wrap`, modals fit, chat switches to single column with a back button, no horizontal page overflow
- [x] Focus states visible; modals close on Esc/backdrop; forms keyboard-submittable

## Known issues (accepted for a frontend demo)
- Dashboard KPI numbers are static snapshots (lists/details are live)
- A few long illustrative sentences (stored-content style) remain EN-only by BRD language rule
- Reports month segments (May/Jun) jump/toggle visually; only July has full data
- Frontend guards are demo-grade; real enforcement arrives with the Laravel backend
