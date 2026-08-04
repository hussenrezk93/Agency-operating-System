# Agency OS — Phase 0: Document Review & Technical Plan

**Prepared for:** Agency OS Management / Project Owner
**Prepared by:** Implementation Team (Laravel Architecture)
**Baseline documents:** BRD v1.1 (25/07/2026, includes CR-001) · ERD v1.2 ("Corrected", aligned to BRD v1.1)
**Status:** Awaiting approval — no code produced in this phase

> **Baseline note:** early instructions referenced ERD v1.1, but the attached diagram is labelled **ERD v1.2 — Corrected**. This plan is built against **v1.2**.

---

## 1. Concise Project Summary

Agency OS is an **internal, single-tenant, bilingual (AR/EN) web application** that acts as the single source of truth for how work moves between company departments.

The core loop: a **Manager** or **Team Leader (TL)** creates a task (standalone or inside a project) and selects the first department. The task lands with that department's **active** TL in `Waiting Assignment`. The TL assigns it to exactly **one** employee (or to themselves), sets a start and end date (end time fixed at **23:59 Africa/Cairo**), and the employee executes, attaches **at least one output link**, and submits. The TL reviews — `Approve` or `Request Changes` (comment mandatory) — and after approval either **sends the task to the next allowed department** or **finishes** it. Completed and cancelled work is permanently read-only.

Around this loop the system provides: role-based permissions enforced server-side, temporary TL delegation, project/client management, an optional per-project WhatsApp **invite-link distribution** mechanism (no WhatsApp API), dual-channel notifications (in-app authoritative + email best-effort), internal chat with 2-hour email digests, monthly on-time performance scoring, and an **append-only audit log visible only to Admin**.

**Explicitly out of scope (v1):** file uploads, client portal, self-registration, reopening completed work, WhatsApp Business API integration, email-based password recovery, payroll/attendance, two departments working the same step in parallel.

---

## 2. Module List

| # | Module | Primary responsibility |
|---|---|---|
| 1 | **Auth** | Username/password login, forced first-login password change, session security, rate limiting |
| 2 | **Users** | User CRUD (no self-registration), activation/deactivation, personal email + verification |
| 3 | **Roles & Access** | Static role model (Admin/Manager/TL/Employee), Policies, Gates, middleware |
| 4 | **Departments** | Department CRUD, activation/deactivation, membership |
| 5 | **Leadership** | Primary TL, Temporary TL lifecycle, permission transfer & restoration |
| 6 | **Routing Rules** | `department_routes` — which department may send to which (Admin-owned) |
| 7 | **Output Access** | `department_output_access` — cross-department output visibility (Admin-owned) |
| 8 | **Clients** | Client CRUD, active/inactive, inline creation during project creation |
| 9 | **Projects** | Project lifecycle, participating departments, reference links |
| 10 | **WhatsApp Invites** | Link versioning, membership derivation, invite fan-out, delivery ledger |
| 11 | **Tasks** | Task creation, drafts, reference links, priority, task code, current step pointer |
| 12 | **Task Steps** | Per-department step, sequence, workflow status, deadline status |
| 13 | **Assignments** | One active assignee per step, start/due dates, first-view tracking, reassignment |
| 14 | **Outputs & Comments** | Immutable output links, step-scoped private comments |
| 15 | **Review & Transitions** | Approve / Request Changes / Send to Next / Finish / Cancel / Hold / Resume / Redirect |
| 16 | **Notifications** | In-app notification records + per-channel delivery tracking |
| 17 | **Mail Delivery** | Provider-agnostic mail layer, queue, retries, bounce handling, verification tokens |
| 18 | **Chat** | 5 conversation types, text/link only, delete-for-all, 2-hour digest batching |
| 19 | **Dashboards & Search** | Role-specific dashboards, task search, Urgent-first ordering |
| 20 | **Performance** | Monthly snapshots for employee / TL personal / TL team / department |
| 21 | **Audit** | Append-only audit log, before/after values, Admin-only viewer |
| 22 | **Localization & UI** | AR/EN translation files, RTL/LTR, central design tokens |

---

## 3. User Roles

| Role | Scope | Never does |
|---|---|---|
| **Admin** | Global configuration: routing rules, output-visibility rules, Manager accounts + their personal email, Manager password reset, system settings, Audit Log | Daily task operations, reading chat content, assigning/reviewing work |
| **Manager** | Full operational control: users (Employee/TL), departments, Temporary TL, clients, projects, tasks, redirect, cancel, all reports; reviewer of self-assigned TL work | Not required to be a task executor |
| **Team Leader** | One department only. Creates tasks/projects, receives department work, assigns, sets deadlines, reviews, routes onward, department reports | Cannot reset passwords, cannot approve own execution, cannot route to non-permitted departments |
| **Employee** | One department only. Executes assigned steps, adds outputs, comments, submits, views own monthly score | Cannot create tasks/projects, cannot route, cannot approve |

**Design decision (Q2):** roles are a **static enum backed by the `roles` table**, not a dynamic permission engine. The BRD's "Admin manages permissions" is satisfied entirely by `department_routes` + `department_output_access`. No `permissions` / `role_permissions` tables are introduced.

---

## 4. Permissions Matrix

Legend: Y = full · C = conditional (condition stated) · N = denied

### 4.1 Administration

| Action | Admin | Manager | TL | Employee |
|---|---|---|---|---|
| Manage system settings | Y | N | N | N |
| Manage `department_routes` | Y | N | N | N |
| Manage `department_output_access` | Y | N | N | N |
| Create/disable Manager accounts | Y | N | N | N |
| Reset Manager password | Y | N | N | N |
| Create/disable Employee & TL accounts | N | Y | N | N |
| Reset Employee/TL password | N | Y | N | N |
| Create/disable departments | N | Y | N | N |
| Assign primary TL | N | Y | N | N |
| Assign / end Temporary TL | N | Y | N | N |
| View Audit Log | Y | N | N | N |
| Set/edit another user's personal email | C — Managers only | C — Employees & TLs only | N | N |
| Edit own personal email | Y | Y | Y | Y |

### 4.2 Clients & Projects

| Action | Admin | Manager | TL | Employee |
|---|---|---|---|---|
| Create client | N | Y | C — during project creation (Q6) | N |
| Edit client / set inactive | N | Y | N | N |
| Create project | N | Y | C — own dept + Admin-allowed depts only | N |
| Edit project (Active only) | N | Y | C — creator only | N |
| Add/remove participating department | N | Y | C — creator, from allowed set | N |
| Add project reference links | N | Y | C — creator | N |
| Complete project | N | Y | C — creator | N |
| Cancel project | N | Y | C — creator (Q7) | N |
| Add/change WhatsApp link | N | Y | C — creator, if TL, project Active | N |
| View project | Y — all | Y — all | C — participating depts | C — only via assigned task |

### 4.3 Tasks & Workflow

| Action | Admin | Manager | TL | Employee |
|---|---|---|---|---|
| Create task | N | Y — any department | C — own dept or Admin-allowed dept | N |
| Edit task data | N | C — creator, before first assignment | C — creator, before first assignment | N |
| Change priority | N | C — draft only | C — draft only | N |
| Delete draft | N | C — creator, pre-workflow | C — creator, pre-workflow | N |
| Assign employee / self-assign | N | C — operational need (Q8) | Y — own department | N |
| Set start/end date | N | C — operational need | Y — own department | N |
| Edit step deadline after assignment | N | C — operational need | Y — own department, no reason required | N |
| Add output link | N | Y | C — when self-assigned | Y — own active step |
| Submit step | N | Y | C — when self-assigned | Y — own active step |
| Approve / Request Changes | N | C — only when TL self-assigned | Y — own department's current step | N |
| Send to Next Department | N | Y | Y — from allowed set only | N |
| Finish Task | N | Y | Y — after approval | N |
| On Hold / Resume | N | Y | C — only if task creator | N |
| Redirect | N | Y — reason mandatory | N | N |
| Cancel task | N | Y — reason mandatory | C — only if task creator | N |
| View all tasks & outputs | Y | Y | C — own dept + Admin output rules | C — assigned + prior steps of same task |
| View step comments | N | C — when acting as reviewer (Q9) | C — own department steps | C — own step |
| View first-open time | N | Y | Y — own department | N |

### 4.4 Communication & Reporting

| Action | Admin | Manager | TL | Employee |
|---|---|---|---|---|
| Employee-TL chat | N | N | Y — own dept employees | Y — own TL |
| Department group chat | N | N | Y | Y |
| TL-TL direct chat | N | N | Y | N |
| All-TLs group | N | N | Y | N |
| Manager+TLs group | N | Y | Y | N |
| Delete own message | N | Y | Y | Y |
| Read chat content of others | N | C — own conversations only | C — own conversations only | C — own conversations only |
| Own monthly score | N | Y | Y | Y |
| Department report | N | Y — all | Y — own | N |
| Cross-department comparison | N | Y | N | N |

---

## 5. Task State-Transition Table

Per ERD v1.2, **workflow status lives on `task_steps`**, **lifecycle status lives on `tasks`**, and **deadline status is a separate field**. They never overwrite each other.

### 5.1 Task lifecycle (`tasks.lifecycle_status`)

| From | Event | Actor | To | Guards | Side effects |
|---|---|---|---|---|---|
| — | Create as draft | Manager / TL | `draft` | first department valid & allowed | audit |
| `draft` | Send to first department | creator | `active` | title, first dept, ≥0 refs | create step #1 `waiting_assignment`, notify dept TL, audit |
| `draft` | Delete | creator | *(hard-deleted)* | never entered workflow | audit (only exception to no-hard-delete) |
| `active` | On Hold | Manager / creator | `on_hold` | reason mandatory | freeze deadline clock, notify employee+TL, audit |
| `on_hold` | Resume | Manager / creator | `active` | — | extend due date by hold duration *(pending Q3)*, notify, audit |
| `active` | Finish Task | current TL | `completed` | current step `approved` | close step, set `completed_at/by`, read-only lock, audit |
| `active` / `on_hold` | Cancel | Manager / creator | `cancelled` | reason mandatory | cancel open steps, stop notifications, audit |
| `active` | Project cancelled | Manager / project creator | `cancelled` | task incomplete | cascade within one transaction, audit |
| `completed` / `cancelled` | *any* | — | **terminal** | reopening blocked at DB + policy | — |

### 5.2 Step workflow (`task_steps.workflow_status`)

| From | Event | Actor | To | Guards | Side effects |
|---|---|---|---|---|---|
| — | Step created | system | `waiting_assignment` | department active & allowed | notify effective TL |
| `waiting_assignment` | Assign employee / self-assign | TL (or temp TL) | `in_progress` | exactly one active assignee; start ≤ due | create assignment, set due 23:59 Cairo, notify assignee |
| `in_progress` | First open | assignee | `in_progress` | once only | set `first_seen_at`, notify TL |
| `in_progress` | Reassign | TL | `in_progress` | end old assignment (`end_reason`), new due date required | notify old + new assignee, audit |
| `in_progress` | Employee disabled | system | `waiting_assignment` | assignment ended | notify TL |
| `in_progress` | Submit | assignee | `under_review` | ≥1 output link | notify reviewer (TL, or Manager if self-assigned) |
| `under_review` | Request Changes | TL / Manager | `changes_requested` | comment mandatory; reviewer ≠ assignee | returns to **same** assignee, notify |
| `changes_requested` | Resubmit | assignee | `under_review` | ≥1 output link | notify reviewer |
| `under_review` | Approve | TL / Manager | `approved` | reviewer ≠ assignee | set `approved_at`, notify |
| `approved` | Send to Next Department | current TL | *(step closed)* | target in allowed routes, target dept active | new step `waiting_assignment`, set `tasks.current_step_id`, audit |
| `approved` | Finish Task | current TL | *(step closed)* | — | task -> `completed` |
| any open | Redirect | **Manager only** | `redirected` | reason mandatory | new step in target department, audit |
| any open | Task cancelled | Manager / creator | `cancelled` | — | end active assignment, audit |

### 5.3 Deadline status (`task_steps.deadline_status`) — independent axis

| Value | Trigger |
|---|---|
| `not_started` | step exists, no assignment yet |
| `on_time` | assigned, now < due − 24h |
| `due_soon` | now >= due − 24h and now <= due (reminder fired once) |
| `overdue` | now > due and step not approved/closed |
| `closed` | step approved, cancelled, or redirected |
| `paused` (needs decision) | **not present in ERD v1.2** — required if Hold pauses the clock (see Q3/Conflict #2) |

---

## 6. Project State-Transition Table

| From | Event | Actor | To | Guards | Side effects |
|---|---|---|---|---|---|
| — | Create | Manager / TL | `active` | one client; ≥1 participating dept; `started_at` = now | derive membership; if link present, fan-out invites v1; audit |
| `active` | Add department | Manager / creator | `active` | dept active, not already linked (UQ) | invite **new dept members only**, audit |
| `active` | Remove department | Manager / creator | `active` | — | stop future invites, notify TL for manual WhatsApp removal, audit |
| `active` | Add / change WhatsApp link | Manager / creator (if TL) | `active` | valid `chat.whatsapp.com` URL | new version row, `is_current` flip, resend to **all current members once**, audit |
| `active` | Remove WhatsApp link | Manager / creator | `active` | — | version row `action_type=removed`, no invites, audit |
| `active` | New user joins participating dept | system | `active` | project active + link exists | auto-invite that user, audit |
| `active` | Complete | Manager / creator | `completed` | manual only | read-only; blocks new tasks; blocks invites; audit |
| `active` | Cancel | Manager / creator (Q7) | `cancelled` | reason | **cancel all incomplete tasks (single transaction)**; blocks invites; audit |
| `completed` / `cancelled` | *any* | — | **terminal** | no reopen, link read-only | — |

---

## 7. Scheduled Jobs (Laravel Scheduler — all evaluated in `Africa/Cairo`)

| Command | Frequency | Purpose | Idempotency guard |
|---|---|---|---|
| `agencyos:deadlines-due-soon` | every 15 min | Fire 24h-before reminders; set `deadline_status=due_soon` | one reminder per assignment (delivery ledger) |
| `agencyos:deadlines-overdue` | every 15 min | Mark `overdue`, fire overdue notice | one overdue notice per assignment |
| `agencyos:chat-digests` | every 15 min | Close due 2-hour windows per user, dispatch digest if `message_count > 0` | `chat_digest_batches` window keys |
| `agencyos:temp-tl-activate` | daily 00:05 | Activate temp assignments starting today; transfer personal steps; notify | `is_active` flag |
| `agencyos:temp-tl-expire` | daily 00:05 | End expired temp assignments; restore primary TL; notify | `is_active` flag |
| `agencyos:performance-snapshot` | monthly, 1st 00:30 | Build `monthly_performance_snapshots` for previous month | unique (user/dept, month_start) |
| `agencyos:performance-refresh` | daily 01:00 | Refresh current-month provisional snapshot for dashboards | upsert |
| `agencyos:email-retry-sweep` | every 10 min | Re-queue `failed` deliveries below max attempts using `next_attempt_at` | `attempt_count` cap |
| `agencyos:prune-verification-tokens` | daily 02:00 | Delete consumed/expired tokens > 30 days | — |
| `agencyos:queue-health-report` | daily 07:00 | Notify Manager of repeated delivery failures/bounces | — |

---

## 8. Queued Jobs

| Job | Queue | Triggered by | Notes |
|---|---|---|---|
| `SendNotificationEmailJob` | `mail` | every notification (except chat messages) | creates/updates `notification_deliveries`; failure never blocks workflow |
| `SendEmailVerificationJob` | `mail` | user create / email change | 24h token |
| `SendChatDigestEmailJob` | `mail` | digest scheduler | one per batch |
| `FanOutProjectInvitesJob` | `default` | link created/changed, dept added, project created | chunked; dispatches per-recipient child jobs |
| `SendProjectInviteJob` | `mail` | fan-out | writes `project_invite_deliveries` (UQ project+user+version+channel) |
| `RecalculatePerformanceJob` | `reports` | step closure / hold / cancel | debounced per user+month |
| `TransferTemporaryTlWorkloadJob` | `default` | temp TL activation/expiry | transactional |
| `CascadeProjectCancellationJob` | `default` | project cancel (large projects) | transactional chunks |
| `ProcessMailWebhookJob` | `mail` | provider webhook | maps to `sent/failed/bounced` |
| `WriteAuditLogJob` | `audit` | high-volume events only | ordered, append-only |

---

## 9. Events & Listeners

| Event | Listeners |
|---|---|
| `TaskCreated` | AuditLog, NotifyDepartmentTl |
| `TaskStepCreated` | AuditLog, NotifyDepartmentTl, InitializeDeadlineStatus |
| `TaskStepAssigned` / `TaskStepReassigned` | AuditLog, NotifyAssignee, NotifyPreviousAssignee, SetDeadlineStatus |
| `TaskStepFirstViewed` | AuditLog, NotifyTeamLeader |
| `TaskStepDeadlineChanged` | AuditLog (before/after), NotifyAssigneeAndTl, RecomputeDeadlineStatus |
| `TaskStepSubmitted` | AuditLog, NotifyReviewer |
| `TaskStepChangesRequested` | AuditLog, NotifyAssignee |
| `TaskStepApproved` | AuditLog, NotifyStakeholders, RecalculatePerformance |
| `TaskTransferred` | AuditLog, CreateNextStep, NotifyNextDepartmentTl |
| `TaskCompleted` | AuditLog, NotifyStakeholders, LockTask, RecalculatePerformance |
| `TaskCancelled` | AuditLog, NotifyStakeholders, CloseOpenSteps, RecalculatePerformance |
| `TaskPutOnHold` / `TaskResumed` | AuditLog, PauseOrExtendDeadline, NotifyAssigneeAndTl |
| `TaskRedirected` | AuditLog, CloseCurrentStep, CreateRedirectedStep, NotifyParties |
| `ProjectCreated` | AuditLog, FanOutProjectInvites *(if link)* |
| `ProjectDepartmentAdded` / `Removed` | AuditLog, FanOutProjectInvites / StopInvites+NotifyTl |
| `ProjectWhatsappLinkChanged` | AuditLog (version), FanOutProjectInvites |
| `ProjectCompleted` / `ProjectCancelled` | AuditLog, LockProject, CascadeCancelTasks, StopInvites |
| `ClientCreated` / `ClientUpdated` | AuditLog (before/after) |
| `UserCreated` / `UserDisabled` | AuditLog, SendEmailVerification, ReleaseOpenSteps, StopInvites+NotifyTl |
| `UserDepartmentChanged` | AuditLog, NotifyPreviousTlToRemoveFromGroups, FanOutNewDepartmentInvites |
| `UserEmailChanged` | AuditLog (before/after), ClearVerification, SendEmailVerification |
| `EmailVerified` / `EmailVerificationFailed` | AuditLog, EnableEmailChannel |
| `EmailDeliveryFailed` / `EmailBounced` | AuditLog, ScheduleRetry, AlertManagerOnRepeatedFailure |
| `TemporaryTlAssigned` / `TemporaryTlEnded` | AuditLog, TransferWorkload, NotifyDepartment |
| `PasswordReset` | AuditLog, ForcePasswordChangeFlag |
| `ChatMessageSent` | CreateInAppNotification, AddToDigestBatch |
| `ChatMessageDeleted` | AuditLog **(event only, no content)**, ClearContentForAll |

---

## 10. Notifications & Recipients

Rule: **every in-app notification has an email counterpart, except chat messages (2-hour digest).** In-app is the official record; email is best-effort and never blocks a state transition.

| Event | In-app recipients | Email | Timing |
|---|---|---|---|
| Task arrives at department | Effective TL (temp TL if active) | Yes | immediate |
| Employee assigned / reassigned | New assignee (+ previous assignee on reassign) | Yes | immediate |
| First task open by employee | Department TL | Yes | immediate |
| Step submitted for review | TL — or **Manager** if TL self-assigned | Yes | immediate |
| Request Changes | Assignee | Yes | immediate |
| Step approved / transferred | Assignee, current TL, next TL | Yes | immediate |
| Deadline in 24h | Assignee + TL **only** | Yes | immediate |
| Deadline exceeded | Assignee + TL **only** | Yes | immediate |
| Deadline modified | Assignee + TL | Yes | immediate |
| On Hold / Resume | Assignee + TL | Yes | immediate |
| Redirect / Cancel | Affected parties (assignee, both TLs, creator) | Yes | immediate |
| Task completed | Creator, participating TLs | Yes | immediate |
| Project cancelled | Participating TLs + assignees of cancelled tasks | Yes | immediate |
| Temporary TL assigned / ended | Department members, primary TL, temp TL | Yes | immediate |
| WhatsApp invite (project) | Derived project members | Yes | immediate |
| Manual WhatsApp removal required | Previous department TL | Yes | immediate |
| Email verification | Account owner | **email only** | immediate |
| New chat message | Conversation members | digest | in-app immediate, **email every 2 hours** |
| Repeated email failure / hard bounce | Manager (+ audit) | Yes | immediate |

---

## 11. Database Tables (from ERD v1.2) — 32 tables

**Identity & Organization (6)**
`roles` · `departments` · `users` · `department_leadership_assignments` · `department_output_access` · `department_routes`

**Clients & Projects (6)**
`clients` · `projects` · `project_departments` · `project_links` · `project_whatsapp_link_versions` · `project_invite_deliveries`

**Tasks & Workflow (10)**
`tasks` · `task_reference_links` · `task_steps` · `task_step_assignments` · `task_step_outputs` · `task_step_comments` · `task_step_reviews` · `task_holds` · `task_redirects` · `task_status_history`

**Notifications & Email (3)**
`notifications` · `notification_deliveries` · `email_verification_tokens`

**Chat (5)**
`chat_conversations` · `chat_members` · `chat_messages` · `chat_digest_batches` · `chat_digest_batch_messages`

**Performance & Audit (2)**
`monthly_performance_snapshots` · `audit_logs`

**Laravel infrastructure (not in ERD, required):** `migrations`, `sessions`, `jobs`, `job_batches`, `failed_jobs`, `cache`, `cache_locks`.
**Deliberately omitted:** `password_reset_tokens` (email password recovery is out of scope).
**Proposed addition (approved):** `settings` (key/value, Admin-owned) — the BRD grants Admin "system settings" but the ERD has no table for it. See Q4.

---

## 12. Required Database Constraints

### 12.1 Unique & composite unique
1. `roles.code`, `departments.name`, `users.username`, `users.personal_email`, `projects.project_code`, `tasks.task_code`
2. `department_leadership_assignments`: **partial unique** — one `is_active` row with `assignment_type='primary'` per department
3. `department_leadership_assignments`: **`EXCLUDE USING gist`** on `(department_id WITH =, daterange(start_date, end_date, '[]') WITH &&) WHERE assignment_type='temporary' AND is_active` — no overlapping temporary periods
4. `department_leadership_assignments`: partial unique — one active led department per user *(relaxed per Q10 — see below)*
5. `department_routes` UQ `(from_department_id, to_department_id)` + CHECK `from <> to`
6. `department_output_access` UQ `(viewer_department_id, source_department_id)`
7. `project_departments` UQ `(project_id, department_id)`
8. `project_whatsapp_link_versions` UQ `(project_id, version_no)` + partial unique one `is_current` per project
9. `project_invite_deliveries` UQ `(project_id, user_id, link_version_id, channel)`
10. `task_steps` UQ `(task_id, sequence_no)`
11. `task_step_assignments` **partial unique** `(task_step_id) WHERE ended_at IS NULL` — one active assignee per step
12. `notification_deliveries` UQ `(notification_id, channel)`
13. `chat_members` UQ `(conversation_id, user_id)`
14. `chat_digest_batch_messages` composite PK `(batch_id, message_id)`
15. `email_verification_tokens` UQ `token_hash` *(missing in ERD — recommended)*
16. `monthly_performance_snapshots`: partial UQ `(user_id, month_start, snapshot_type) WHERE user_id NOT NULL` and `(department_id, month_start) WHERE department_id NOT NULL`
17. `chat_conversations`: **recommended** deterministic uniqueness for `employee_tl` and `direct_tl` pairs via a normalized `pair_key` column *(missing in ERD — see Q10)*

### 12.2 CHECK constraints
- `task_step_reviews`: `comment IS NOT NULL` when `decision='changes_requested'`
- `chat_messages`: active message must have `body` or `link_url`; deleted message must have both cleared
- `monthly_performance_snapshots`: `user_id` required for employee/tl_personal/tl_team; `department_id` required for department
- `task_step_assignments`: `due_date >= start_date`
- `task_holds`: `ended_at IS NULL OR ended_at >= started_at`
- `projects`: `whatsapp_group_url` null OR matches WhatsApp invite pattern
- `clients.phone` NOT NULL; `tasks.title` NOT NULL

### 12.3 Foreign keys & delete behaviour
- **All FKs `ON DELETE RESTRICT`** — historical integrity is mandatory. Deactivation replaces deletion everywhere except unsent task drafts.
- `tasks.current_step_id` -> `task_steps.task_id` is a **circular reference**: `current_step_id` stays nullable and is set after step insert inside the same transaction (no DEFERRABLE needed).
- Nullable FKs per ERD: `users.department_id`, `tasks.project_id`, `projects.completed_by/cancelled_by/whatsapp_link_updated_by`, `project_invite_deliveries.notification_id/department_id`, `chat_digest_batches.notification_id`, `audit_logs.actor_user_id`.

### 12.4 Indexes (search & reporting)
- `tasks(task_code)`, `tasks(title)` GIN trigram, `projects(name)` GIN trigram — BRD §16 search
- `tasks(priority, created_at DESC)` — Urgent-first ordering
- `task_steps(department_id, workflow_status)`, `task_steps(deadline_status)`
- `task_step_assignments(assignee_id, due_date)` — dashboards + monthly scoring
- `notifications(user_id, is_read, created_at DESC)`
- `notification_deliveries(status, next_attempt_at)` — retry sweep
- `chat_messages(conversation_id, created_at DESC)`
- `audit_logs(entity_type, entity_id, created_at DESC)`, `audit_logs(actor_user_id)`, GIN on `metadata`
- `project_invite_deliveries(project_id, status)`

### 12.5 Append-only enforcement
`audit_logs` and `task_step_outputs` immutability enforced by **PostgreSQL trigger** raising on `UPDATE`/`DELETE`, plus a revoked-grant DB role in production, plus model-level guards. Application-level protection alone is insufficient.

---

## 13. Conflicts, Gaps & Risks Found at Phase 0 (BRD <-> ERD <-> Instructions)

> These were the open items raised during initial document review, together with the resolution actually adopted (matching the current implementation and `CHANGE-REQUEST-REGISTER.md` / `APPROVED-DECISIONS-Q1-Q30.md`).

### [BLOCKING] Conflict 1 — Document baseline
Early instructions cited ERD v1.1; the attachment was labelled ERD v1.2. **Resolved:** BRD v1.1 + ERD v1.2 is the frozen baseline.

### [BLOCKING] Conflict 2 — On Hold: does the deadline pause? *(most serious)*
The BRD contradicted itself in three places: the main acceptance criteria said hold pauses the clock and extends the end date by the hold duration; two inline notes said the opposite. ERD v1.2 and later instructions sided with pause + extend.
**Resolved:** **pause + auto-extend** — matches ERD and the majority of BRD; the contradicting notes are treated as stale.

### [BLOCKING] Conflict 3 — Status vocabulary mismatch
Early instructions listed workflow statuses `Assigned`/`Transferred` and deadline statuses `Near Deadline`/`Paused`, none of which exist in the BRD or ERD.
**Resolved:** adopt ERD v1.2 enums verbatim; "Assigned" = `in_progress` before `first_seen_at`; "Transferred" = closed step + new step. `deadline_status` gains a `paused` value (ERD amendment) since Conflict 2 resolved as "pause".

### [BLOCKING] Conflict 4 — `Overdue` is listed as a task status in BRD §10
BRD §10 places `Overdue` alongside workflow statuses, while ERD v1.2 requires workflow and deadline status to be strictly separate.
**Resolved:** `Overdue` is a `deadline_status` value only, never a workflow status — a step can be `in_progress` **and** `overdue` at once.

### [BLOCKING] Conflict 5 — Where does the step deadline live?
The ERD stores `start_date`/`due_date` on `task_step_assignments`, not on `task_steps`, which made monthly scoring and hold extension ambiguous across reassignment.
**Resolved:** add `task_steps.current_start_date` and `current_due_at (timestamptz)`, maintained from the active assignment; scoring and holds use the step.

### [DECISION NEEDED] Conflict 6 — Temporary TL eligibility vs. "one active led department per user"
ERD enforced one active leadership row per user, which forbade the most natural delegation pattern (another department's TL covering as Temporary TL).
**Resolved:** relaxed to "one active **primary** leadership per user" — a TL may hold one temporary assignment elsewhere.

### [DECISION NEEDED] Conflict 7 — Redirect semantics
Undefined whether Redirect may target a previously visited department and whether it must obey `department_routes`.
**Resolved:** Manager Redirect **bypasses** routing rules and may target any active department including a previous one; the new step gets `max(sequence_no)+1` so history stays linear.

### [DECISION NEEDED] Conflict 8 — "Skip Step" does not exist
No such action exists in the BRD or ERD. **Resolved:** out of scope; would require a new Change Request.

### [DECISION NEEDED] Gap 9 — `department_output_access.scope = final_only` and `task_step_outputs.is_final`
Neither the BRD "final output" concept was fully defined. **Resolved:** the last output added before the approved submission is `is_final` automatically.

### [DECISION NEEDED] Gap 10 — Email change has no "pending email" holding field
Overwriting `personal_email` immediately on edit risked locking the notification channel on a typo.
**Resolved:** added `users.pending_email` + `email_verification_tokens.email`; promote only on verification.

### [DECISION NEEDED] Gap 11 — Performance attribution when work moves between people
**Resolved:** charge the assignee active at the moment the step closes (simplest, auditable).

### [DECISION NEEDED] Gap 12 — "The Manager" is treated as a single person
The BRD sometimes refers to "the Manager" as one person, but the system supports multiple Managers.
**Resolved:** all active Managers receive the relevant notifications, and any Manager may review self-assigned TL work.

### [DECISION NEEDED] Gap 13 — Disabled department holding active tasks
**Resolved:** steps remain `waiting_assignment` in the inactive department and surface in a Manager "needs reassignment" queue, cleared via Redirect.

### [CLARIFICATION] Gap 14 — `users.status = 'on_leave'`
**Resolved:** active-but-not-assignable; no invite/notification changes.

### [CLARIFICATION] Gap 15 — Audit metadata vs. Admin confidentiality
BRD §19 states Admin gets no access to task content or chat content, yet `audit_logs.metadata jsonb` could naturally carry comment text, output URLs, and task titles.
**Resolved:** metadata whitelist — IDs, status transitions, field names, and before/after values for configuration and identity fields only. Comment bodies, chat text, and output URLs are never persisted in audit metadata (record `output_id` instead). Enforced in code, not just convention.

### [CLARIFICATION] Gap 16 — Deadline timezone storage
`task_step_assignments.due_date` is a `date` while the rule is 23:59 Africa/Cairo, and Egypt reintroduced DST in 2023.
**Resolved:** store both the `date` (business meaning) and a computed `due_at timestamptz` (comparison), written inside the same transaction.

### [CLARIFICATION] Gap 17 — Employee reference visibility
**Resolved:** approved outputs of previous, closed steps only — never the in-flight step of another department.

### [CLARIFICATION] Gap 18 — TL project-creation permission
**Resolved:** all TLs may create projects and tasks, constrained to their own department plus Admin-allowed target departments. No extra permission flag.

### [CLARIFICATION] Gap 19 — Missing minor items
- `settings` table added for Admin system settings.
- Unique index added on `email_verification_tokens.token_hash`.
- Uniqueness rule added to prevent duplicate `employee_tl` / `direct_tl` conversations.
- `projects.whatsapp_link_version` is maintained transactionally as a denormalized cache; `project_whatsapp_link_versions` is the single source of truth.
- `projects.project_code` format: `PRJ-YYYY-NNNN`; `tasks.task_code`: `TSK-YYYY-NNNNN`.

---

## 14. Laravel Folder Structure

Modular monolith — domain folders under `app/Domains` (superseded in the current codebase by `app/Services`, `app/Models`, `app/Http/Controllers`, `app/Policies`, etc. — see the live tree for the as-built structure), thin controllers, business rules in services, transitions in state machines.

**Front-end / branding:**
- All brand colours declared once as CSS custom properties, exposed to Tailwind via `@theme` tokens.
- Blade templates use semantic token classes only — no raw hex literals.
- Direction handled by `<html dir="rtl|ltr">` + Tailwind logical properties (`ms-*`, `me-*`, `ps-*`, `pe-*`).
- Shared component library (button, table, badge, card, form control, modal, empty state) used by every screen.
- White page background, very light gray surfaces, orange reserved for primary actions/active nav/table headers/section titles; status colours: green success, amber warning, red overdue/error, blue informational. No dark theme, WCAG AA contrast verified (orange on white needs a darkened shade for small text — token `--color-primary-text: #C2410C`).

---

## 15. Development Roadmap (as approved)

| Phase | Content | Key exit criteria |
|---|---|---|
| **0** | Document review, technical plan (this document) | Written approval + answers to open questions |
| **1** | Laravel + PostgreSQL setup, config, AR/EN localization, RTL/LTR, design tokens, base layouts, auth | Login works in both languages; branding tokens centralized |
| **2** | Migrations (ordered), enums, models, relationships, casts, factories, seeders, DB triggers for append-only | `migrate:fresh --seed` green; all constraints proven by tests |
| **3** | Users, roles, departments, routing rules, output-access rules, primary + Temporary TL | Overlap and single-active-TL constraints proven; delegation verified |
| **4** | Clients, projects, project departments, links, WhatsApp versioning + invite fan-out + delivery ledger | Every CR-001 acceptance criterion demonstrable |
| **5** | Tasks, drafts, steps, assignment, deadlines, first-view, outputs, comments, history | A task travels end-to-end through one department |
| **6** | Approve, Request Changes, transfer, finish, cancel, hold, resume, redirect — all transactional | Full state-machine test matrix green; illegal transitions rejected |
| **7** | Notifications, email verification, queue, retries, delivery tracking, bounce handling, chat digests | Mail outage does not block any transition (proved by test) |
| **8** | Chat: 5 conversation types, delete-for-all, digest batching | Deleted content unreachable; audit records event without content |
| **9** | Dashboards (4 roles), search, Urgent ordering, monthly snapshots, employee/TL/department reports | Score formula verified against BRD worked examples; N/A handled |
| **10** | Audit viewer, security hardening, full automated suite, responsive polish, UAT support | Security checklist signed off; UAT scenarios passed |

Sequencing note: Phases 5 and 6 are the risk concentration. Phases 7 and 8 can run partly in parallel with 9 if two developers are available.

---

## 16. Testing Strategy

**Framework:** PHPUnit, PostgreSQL test database (never SQLite — the plan relies on partial unique indexes, `EXCLUDE`, `interval`, `jsonb`, and triggers), `RefreshDatabase`, frozen clock via `Carbon::setTestNow` pinned to `Africa/Cairo`.

**Layers**
1. **Unit** — performance score calculator (including N/A, hold, cancelled, changes-requested-but-on-time), deadline calculator (23:59 Cairo across a DST boundary), hold extension arithmetic, project membership resolver, routing-rule evaluator.
2. **Feature (workflow)** — one test per row of the state-transition tables in §5-6, including the **negative** path: every illegal transition must be rejected by the service *and* by the policy.
3. **Feature (authorization)** — a matrix test that drives §4 automatically: for each (role x action x ownership context) assert allow/deny.
4. **Feature (constraints)** — assert the DB itself rejects: two active primary TLs, overlapping temporary periods, two active assignees on a step, duplicate project department, duplicate invite per version, duplicate `notification+channel`, `UPDATE` on `audit_logs`, `UPDATE` on `task_step_outputs`.
5. **Integration (mail/queue)** — `Mail::fake()`/`Queue::fake()` for dispatch assertions; a dedicated test forces mail transport failure and asserts the workflow transition still commits and the delivery row records `failed`.
6. **Scheduler** — run each command against seeded fixtures and assert idempotency by invoking twice (no duplicate reminders, no duplicate digests).
7. **Security** — IDOR probes (foreign task/project/message IDs return 403/404, never data), mass-assignment attempts on `role_id`/`status`/`department_id`, XSS payloads in titles/comments/chat rendered escaped, rate-limit enforcement on login and password reset, CSRF absence rejection.
8. **Architecture** — controllers contain no query builders; models are not used in views; no hex colour literals in Blade; no untranslated hardcoded UI strings.
9. **Manual/UAT** — a scripted checklist per phase, executed in both AR and EN, on desktop and mobile widths.

**Coverage targets:** 100% of state transitions and policy branches; >=85% line coverage in application code. Coverage is a floor, not the goal — transition and policy completeness is.

**Seeded UAT dataset:** 4 departments, 1 Admin, 2 Managers, 4 TLs, 12 Employees, 3 clients, 4 projects (one with a WhatsApp link, one completed, one cancelled), ~40 tasks spanning every status, deliberately including overdue, held, redirected, and self-assigned cases.

---

## 17. Security Checklist

**Authentication & sessions**
- [ ] No registration route registered anywhere; self-registration disabled
- [ ] Username + password only; no email-based reset route (BRD §4.2)
- [ ] Bcrypt/Argon2id hashing at configured work factor
- [ ] Temporary password generated with `random_bytes`, `must_change_password` forced by global middleware
- [ ] Session cookie `HttpOnly`, `Secure`, `SameSite=Lax`; session regenerated on login and privilege change; idle + absolute timeout
- [ ] Rate limits: login (per IP + per username), password reset, email verification resend, chat send, invite resend
- [ ] Deactivated / `on_leave` users invalidated mid-session on the next request

**Authorization**
- [ ] Every controller action guarded by a Policy or Gate — no reliance on hidden UI
- [ ] Role checks in middleware **and** policy (defence in depth)
- [ ] All model retrieval scoped by ownership/department before authorization (IDOR)
- [ ] Route-model binding scoped to the parent (`/projects/{project}/tasks/{task}`)
- [ ] Audit Log routes gated to Admin only, with an additional confirmation gate
- [ ] Admin explicitly blocked from task-execution and chat-content endpoints

**Data protection**
- [ ] `$fillable` allow-lists on every model; `role_id`, `status`, `department_id`, `*_by`, `*_at` never mass-assignable
- [ ] `personal_email` classified as PII: minimum access, never exposed in listings to non-privileged roles, never logged in plaintext
- [ ] Verification tokens stored **hashed**, single-use, 24h expiry, consumed atomically
- [ ] Audit metadata whitelist enforced in code (Gap 15) — no comment/chat/output content persisted
- [ ] Deleted chat content physically cleared, not soft-hidden

**Input & output**
- [ ] Form Requests on 100% of write endpoints; validation messages translated
- [ ] URL validation: scheme allow-list (`https` only), host allow-list for WhatsApp invites (`chat.whatsapp.com`), length caps, no `javascript:`/`data:`
- [ ] Blade auto-escaping everywhere; raw unescaped output banned by architecture test
- [ ] Content-Security-Policy, `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy`
- [ ] CSRF on all state-changing routes

**Database & infrastructure**
- [ ] Application DB role has no `DROP`/`ALTER`; a separate role owns migrations
- [ ] Triggers block `UPDATE`/`DELETE` on `audit_logs` and `task_step_outputs`
- [ ] All critical workflows wrapped in `DB::transaction` with correct isolation and row locking on the step
- [ ] Backups + restore drill before UAT; encrypted at rest
- [ ] `.env` secrets outside the repo; mail provider credentials rotated and provider-swappable
- [ ] SPF, DKIM, DMARC configured for the sending domain; send-rate throttling (BRD §20)
- [ ] Structured logging for security-sensitive operations; log data separated from audit data

---

## 18. Resolved Questions (from original Phase 0 review)

These were the Phase 0 open questions; all now carry the resolution actually implemented (cross-check against `APPROVED-DECISIONS-Q1-Q30.md` for the authoritative record, since numbering differs slightly between this technical plan and the approved-decisions register).

- Baseline: BRD v1.1 + ERD v1.2 — confirmed.
- Roles are static (no dynamic permission engine) — confirmed.
- A `settings` table was added for Admin system settings — confirmed.
- On Hold pauses the deadline and auto-extends the end date — confirmed.
- ERD enum vocabulary adopted verbatim; "Assigned" -> `in_progress`, "Transferred" -> closed step + new step.
- `Overdue` is strictly a deadline status, never a workflow status.
- `task_steps.current_start_date` / `current_due_at` added as the authoritative step deadline.
- `paused` added to `deadline_status`.
- A TL of department A may be Temporary TL of department B (relaxed to one active **primary** leadership per user).
- A non-TL Employee may **not** be appointed Temporary TL.
- Performance score is charged to the assignee active when the step closes.
- Notifications to "the Manager" go to all active Managers; any Manager may review self-assigned TL work.
- `on_leave` means visible and active, but not assignable to new steps.
- Manager Redirect bypasses `department_routes` and may target a previously visited department.
- "Skip Step" is out of scope.
- Manager may assign employees and set deadlines directly, for operational continuity; every such action is audited.
- A TL who created a task may hold/cancel it themself, per BRD §10.
- `project_code` format: `PRJ-YYYY-NNNN`; `task_code`: `TSK-YYYY-NNNNN`.
- `users.pending_email` + `email_verification_tokens.email` added so a mistyped address cannot break the channel.
- `task_step_outputs.is_final`: last output at approved submission = final; `final_only` scope supported but seeded as `all_outputs`.
- Employee sees approved outputs of closed previous steps only.

---

## Phase 0 Exit

Phase 0 is closed. All thirty business questions (Q1-Q30) are answered and recorded in `APPROVED-DECISIONS-Q1-Q30.md`; any deviation from this technical plan or the BRD is logged in `CHANGE-REQUEST-REGISTER.md`. Remaining open items are deployment/configuration values only, tracked in `OPEN-DECISIONS.md`, and do not block Phase 1B implementation work.
