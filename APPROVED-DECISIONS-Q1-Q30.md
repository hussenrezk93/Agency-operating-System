# Agency OS — Approved Decision Register (Q1–Q30)

**Status:** APPROVED by the product owner · **Date recorded:** 25 July 2026
**Baseline:** `Agency OS_BRD_AR_v1.1_Final_Corrected.pdf` (Q1) + corrected ERD v1.2 + this register.

> The BRD PDF carries an internal *Draft* label. The product owner has explicitly approved
> it as the **final implementation baseline**; the label is cosmetic and does not affect its
> authority. Source precedence: **BRD → ERD → this register → approved frontend demo →
> existing backend foundation.**

Legend for **Phase 1A.1 status**:
`✅ implemented` · `🧱 schema/authority in place, behavior in 1B` · `⏸ deferred to 1B` · `📄 documented only`

---

## Classification

### Confirmed or clarified from the BRD
Q1 baseline · Q11/Q21 effective-TL assignment authority · Q14 primary TL view-only during
leave · Q12 Manager reviews TL self-assignment · Q22 project complete/cancel authority ·
Q24 pending personal email verification · Q25 multiple approved outputs ·
Q26 approved previous-step output visibility · Q27/Q28 company email + queue behavior ·
Q30 UI Kit checkpoint.

### Approved Change Requests / additions
CR-002 Employee may become Temporary TL (Q2) · CR-003 effective role changes temporarily to
Team Leader (Q2) · CR-004 Temporary TL must be from the same department (Q3) ·
CR-005 Temporary TL retains existing personal tasks (Q4) · CR-006 My Tasks page triggers
automatic Seen (Q19) · CR-007 Project On Hold (Q23) · CR-008 storage-driven configurable
retention model (Q29).
Full detail: `CHANGE-REQUEST-REGISTER.md`.

---

## Temporary Team Leader

| # | Decision | 1A.1 status |
|---|---|---|
| **Q2** | An Employee may be appointed Temporary TL. Effective role becomes Team Leader for the period, then automatically returns to Employee. The original role must never be lost; transitions are audited with history. | ✅ `users.base_role_id` preserves the substantive role, `user_role_transitions` records every change, `RoleTransitionService` performs it transactionally and idempotently |
| **Q3** | Only an Employee **of that same department** may be appointed. | ✅ `TemporaryLeadershipService::assertEligible()` + tests |
| **Q4** | Existing Employee tasks stay assigned to them — never returned to Waiting Assignment. | ⏸ needs task tables (no code touches assignments today) |
| **Q5** | The Temporary TL may self-assign department tasks in their TL capacity; existing Employee tasks stay active. | ⏸ Phase 1B |
| **Q6** | One temporary assignment at a time, own department only. | ✅ eligibility check + partial unique indexes + EXCLUDE constraint |
| **Q7** | On end: leadership returns to the Primary TL, pending reviews return, the user returns to Employee, personally assigned tasks stay with them. | 🧱 role restoration ✅; review hand-back ⏸ 1B |
| **Q8** | Early termination is immediate, transactional and audited. | ✅ `endEarly()` + Manager-only endpoint with 403 tests |
| **Q9** | Temporary TL is used **only** when the Primary TL is on leave — not for workload sharing. | ✅ mandatory `reason` column + documented rule |
| **Q10** | When the Primary TL starts leave, their self-assigned active tasks transfer to the Temporary TL preserving history, original assignee, reason, deadline and audit. | ⏸ Phase 1B (explicit `TODO(Phase 1B · Q10)` hook in the service) |
| **Q11** | Temporary TL receives full TL operational permissions, never Manager-only ones. | 🧱 `Department::effectiveLeader()` ✅ single authority + `canActAsLeaderOf()`; task-level permissions land in 1B |
| **Q12** | A stage self-assigned by the Temporary TL is reviewed by the **Manager**. | 📄 recorded in the `TaskPolicy` target matrix |
| **Q13** | Future-dated assignments activate and end automatically; no manual step. | ✅ `activation_state` + `agencyos:process-leadership-transitions` (idempotent, scheduled daily 00:05 Africa/Cairo) |
| **Q14** | During leave the Primary TL may log in and view, but is **View Only** for their department. | ✅ implemented and **defect-fixed** in the 1A.1 correction pass — `User::isViewOnlyLeader()` now resolves through `Department::effectiveLeader()`; enforcement on task actions ⏸ 1B |
| **Q15** | If no suitable same-department Employee exists, the leave must not start. | 🧱 appointment-level validation ✅; tying it to a leave record ⏸ 1B |
| **Q16** | The Manager may terminate and immediately appoint a replacement; transactional, never two or zero leaders. | ✅ `replace()` + test asserting exactly one active assignment |
| **Q17** | On replacement: permissions end, user returns to Employee, self-assigned tasks stay theirs, authority moves to the replacement. | 🧱 role + authority ✅; task retention ⏸ 1B |
| **Q18** | Temporary TL may **not** add/move/disable employees or change department membership — Manager-only. | ✅ `TemporaryLeadershipPolicy::manageMembership()` is Manager-only and tested; the user-management screens land in a later phase |

## Automatic Seen

| # | Decision | 1A.1 status |
|---|---|---|
| **Q19** | No Seen button. Opening **My Tasks** marks the employee's own eligible unseen stages as seen; `first_seen_at` written once; reload never overwrites; idempotent; completed/cancelled/inaccessible/unassigned ignored; other users' tasks never touched. **Approved change** vs the BRD wording (which referred to opening an individual task). | ⏸ Phase 1B — recorded as CR-006 and written into the `TaskPolicy` target matrix |
| **Q20** | `first_seen_at` is visible **only to the effective Team Leader** of the department — not the Employee, not the Manager, not other TLs. The event is still audited. | ⏸ 1B. **Correction applied in 1A.1:** the previous `TaskPolicy` docblock wrongly implied Manager visibility; that text is now fixed |

## Task assignment

| # | Decision | 1A.1 status |
|---|---|---|
| **Q21** | Only the **effective TL of the receiving department** selects the employee, self-assigns, sets/changes start and end dates, reassigns. The **Manager must not** directly assign an employee to a stage. Adding an employee to a department stays a Manager-only action. | 🧱 `Department::effectiveLeader()` ✅ is in place. **Correction applied:** the earlier `TaskPolicy` note claiming "Manager: full operational control" and the pre-Phase-1A starter's `assign()` allowing the Manager are both rescinded |

## Project authority

| # | Decision | 1A.1 status |
|---|---|---|
| **Q22** | Manager **or the original creator** (including a TL creator) may complete or cancel. Cancellation requires a reason, cancels unfinished tasks, preserves completed ones, one transaction, stops new WhatsApp invitations, makes the link read-only, fully audited. No reopening. | 🧱 authority ✅ in `ProjectPolicy`; DB requires a reason on cancellation ✅; cascade + WhatsApp effects ⏸ 1B |
| **Q23** | **Project On Hold** added (approved change: BRD lists only Active/Completed/Cancelled). Unfinished tasks pause, no new tasks, timing pauses per hold accounting, history unchanged, reason mandatory, resume restores prior states, completed/cancelled untouched. Must not be visual-only. | 🧱 status ✅ in enum + DB CHECK + policy authority; **pausing behavior deliberately not implemented** — it needs the task tables, and a visual-only implementation is explicitly forbidden |

## Personal email

| # | Decision | 1A.1 status |
|---|---|---|
| **Q24** | Changing a verified email keeps the old address active; the new one is stored **pending**; verification is sent to the pending address; promotion happens only on success; notifications keep using the verified address meanwhile; request and success are audited. | 🧱 `users.pending_email` + `pending_email_requested_at` ✅ (ERD amendment); `User::activeEmail()` ✅; token flow + sending ⏸ (needs the mail queue, Q27/Q28) |

## Outputs

| # | Decision | 1A.1 status |
|---|---|---|
| **Q25** | All output links in an approved submission are Final Outputs — not only the latest. Outputs stay immutable; corrections add a new output and mark the old superseded. | ⏸ 1B (schema note in ERD-IMPACT-NOTES.md) |
| **Q26** | The current assignee sees only **final approved outputs of previous completed steps** of the same task — no drafts, rejected, superseded, unapproved, unrelated tasks, or another stage's private comments. | ⏸ 1B |

## Email delivery

| # | Decision | 1A.1 status |
|---|---|---|
| **Q27** | Send from the company's official account to verified personal emails, over configurable SMTP. Provider-agnostic — no provider hard-coded in domain services. Secrets in environment/deployment config, never the database. | 🧱 `.env.example` uses generic SMTP keys ✅; no provider appears anywhere in code ✅; mail module ⏸ 1B |
| **Q28** | Email goes through a background queue; delivery never blocks task transitions, reviews, assignments, project operations or in-system notifications. Retries, backoff, failed-job storage, delivery status records, idempotent jobs, error recording. In-system notification remains the official channel. | ⏸ 1B (`notification_deliveries` ledger is already in the ERD) |

## Data retention & storage

| # | Decision | 1A.1 status |
|---|---|---|
| **Q29** | No fixed one-year retention. Monitor storage, estimate remaining runway, warn before capacity, run an approved cleanup. Build monitoring, configurable thresholds, retention reporting, runway estimation, safety margin, Admin warnings, cleanup auditing, export-before-deletion. **No automatic destructive deletion** until the numeric parameters are approved. | ⏸ module deferred; the numeric values are recorded as **deployment/SRS configuration decisions** in OPEN-DECISIONS.md, not open business questions |

## UI integration

| # | Decision | 1A.1 status |
|---|---|---|
| **Q30** | Build an approved bilingual UI Kit gallery (EN/AR, LTR/RTL, desktop/mobile, all listed component states) **before** integrating the 51 screens. | 📄 checkpoint recorded; full-screen integration is blocked until approval |

---

## Platform decisions applied in code

| # | Decision | Applied |
|---|---|---|
| Q1 | BRD v1.1 Final Corrected is the baseline | ✅ referenced throughout |
| — | Static roles, single users table, single guard | ✅ `RoleCode` enum, one `web` guard |
| — | Laravel 12 / PHP ^8.2 / PostgreSQL | ✅ `composer.json`, `config/database.php` |
| — | Africa/Cairo timezone | ✅ `config/app.php` |
