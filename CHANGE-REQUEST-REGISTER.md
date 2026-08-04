# Agency OS — Change Request Register

Every entry is an **approved** deviation from, or addition to, `Agency OS_BRD_AR_v1.1_Final_Corrected.pdf`.
Nothing here was decided silently; each row traces to the approved decision register.

Status: `applied 1A.1` = schema/authority landed now · `queued 1B` = implementation waits for the task workflow engine.

---

## CR-001 — Email notifications + WhatsApp project group link
*(already merged into BRD v1.1 — listed for completeness)*

| Field | Detail |
|---|---|
| Previous rule | In-system notifications only; no WhatsApp concept. |
| Approved rule | Verified personal email as an assisting channel; project-level WhatsApp invite link with versioning and a delivery ledger; no WhatsApp API. |
| BRD sections | §4, §7.3, §11.1, §18.1, §19, §20, §22 |
| ERD entities | `users.personal_email`, `users.email_verified_at`, `projects.whatsapp_*`, `project_whatsapp_link_versions`, `project_invite_deliveries`, `notifications`, `notification_deliveries`, `email_verification_tokens`, `chat_digest_batches` |
| Frontend screens | profile, email-verification ×3, notifications, project-details (WhatsApp + deliveries tabs), system-settings |
| Backend impact | mail queue, verification service, invite fan-out |
| Migration impact | `users` columns ✅ present; WhatsApp/notification tables queued 1B |
| Test impact | verification flow, delivery ledger, digest batching |
| Status | partially applied (user columns) · rest **queued 1B** |

---

## CR-002 — An Employee may become Temporary Team Leader

| Field | Detail |
|---|---|
| Previous rule | BRD §12 implies the temporary leader is a Team Leader; the Phase 1A foundation assumed a non-TL could not be appointed. |
| Approved rule | **Q2/Q3** — an Employee of the *same department* may be appointed Temporary TL. |
| BRD sections | §5 (roles), §6 (organization), §12 (temporary TL) |
| ERD entities | `users` (+`base_role_id`), **new** `user_role_transitions`, `department_leadership_assignments` (+`reason`, `activation_state`, `ended_by`, `replaced_by_assignment_id`) |
| Frontend screens | temporary-tl (candidate list must show same-department Employees), departments, users |
| Backend impact | `TemporaryLeadershipService::assertEligible()`, `RoleTransitionService` |
| Migration impact | `..._000011_add_pending_email_and_base_role_to_users_table`, `..._000012_create_user_role_transitions_table`, `..._000013_extend_leadership_assignments_for_approved_decisions` |
| Test impact | `TemporaryLeadershipTest` (eligibility, same-department rejection, TL rejection) |
| Status | **applied 1A.1** |

---

## CR-003 — Effective role changes temporarily to Team Leader

| Field | Detail |
|---|---|
| Previous rule | `users.role_id` was a single, static role; no mechanism for temporary elevation. |
| Approved rule | **Q2** — `role_id` becomes the *effective* role; the substantive role is preserved and restored automatically. The original value must never be destroyed, and every transition is auditable. |
| BRD sections | §5, §12, §19 (audit) |
| ERD entities | `users.base_role_id` (new), `user_role_transitions` (new entity) |
| Frontend screens | any screen rendering the role chip; temporary-tl; profile |
| Backend impact | `RoleTransitionService` (elevate/restore, idempotent, transactional, audited), `User::substantiveRoleCode()`, `User::isTemporarilyElevated()` |
| Migration impact | as CR-002 |
| Test impact | original-role preservation and restoration, audit entries, idempotency |
| Status | **applied 1A.1** |

---

## CR-004 — Temporary TL restricted to the same department

| Field | Detail |
|---|---|
| Previous rule | Phase 0 proposed (Q10 default) allowing a TL of department A to cover department B. |
| Approved rule | **Q3/Q6** — same department only, one active assignment per user. |
| BRD sections | §6, §12 |
| ERD entities | `department_leadership_assignments` (partial unique indexes retained; the stricter "one active led department per user" index is now *correct* rather than provisional) |
| Frontend screens | temporary-tl candidate picker |
| Backend impact | `assertEligible()`; cross-table rule validated in the service (not expressible as a standard SQL CHECK) |
| Migration impact | none beyond CR-002's extension |
| Test impact | cross-department rejection, duplicate-assignment rejection |
| Status | **applied 1A.1** |

---

## CR-005 — Temporary TL retains existing personal tasks

| Field | Detail |
|---|---|
| Previous rule | Frontend demo transferred a TL's personal tasks on delegation; the employee case was undefined. |
| Approved rule | **Q4/Q5/Q7/Q17** — an elevated Employee keeps their own assigned tasks and continues them alongside leadership duties. On end or replacement, those tasks stay with them as Employee. Separately (**Q10**) the *Primary TL's* self-assigned tasks transfer to the Temporary TL. |
| BRD sections | §9, §12 |
| ERD entities | `task_step_assignments`, `task_status_history` |
| Frontend screens | tl-dashboard, tasks (My Tasks), task-details |
| Backend impact | future `TaskWorkflowService` + `TemporaryLeadershipService` hooks (explicit `TODO(Phase 1B · Q10)` markers already in place) |
| Migration impact | none in 1A.1 |
| Test impact | task-retention and transfer tests in 1B |
| Status | **queued 1B** |

---

## CR-006 — Automatic Seen is triggered by opening My Tasks

| Field | Detail |
|---|---|
| Previous rule | BRD §9.4 / §22.8: the system records the first time the employee **opens the task**. |
| Approved rule | **Q19** — opening the **My Tasks page** marks all of that employee's eligible unseen stages as seen. No Seen/Acknowledge button. `first_seen_at` written once, reload-safe, idempotent; completed, cancelled, inaccessible and unassigned tasks ignored; other users' tasks never modified. |
| BRD sections | §9, §16.2, §19, §22.8 |
| ERD entities | `task_step_assignments.first_seen_at`, `audit_logs` |
| Frontend screens | tasks (My Tasks), employee-dashboard, task-details, tl-dashboard |
| Backend impact | future `SeenService` invoked on the My Tasks endpoint; audit written once |
| Migration impact | none extra (`first_seen_at` already in the ERD) |
| Test impact | idempotency, no-overwrite on reload, isolation from other employees, exclusion of closed tasks |
| Status | **queued 1B** (documented; the demo already behaves this way) |

---

## CR-007 — Project On Hold

| Field | Detail |
|---|---|
| Previous rule | BRD §7.2 and §22: project statuses are **Active, Completed, Cancelled only**. |
| Approved rule | **Q23** — a project may be placed On Hold by the Manager or the creator, with a mandatory reason. Unfinished tasks pause, no new tasks may be created, timing pauses per the approved hold accounting, history is unchanged, resume restores prior states, completed/cancelled tasks are untouched. Transactional and audited. **Must not be a visual-only status.** |
| BRD sections | §7.2, §10, §11, §17, §21, §22 — **BRD update required** |
| ERD entities | `projects.status` (+`on_hold`), and a project-level hold record analogous to `task_holds` (proposed: `project_holds`) |
| Frontend screens | projects, project-details, tasks, reports |
| Backend impact | `ProjectPolicy::hold/resume` ✅ authority; future `ProjectHoldService` + interaction with deadline accounting |
| Migration impact | `..._000014_add_confirmed_enum_check_constraints` allows `on_hold` ✅; `project_holds` table queued 1B |
| Test impact | status acceptance ✅ now; pausing/resume behavior in 1B |
| Status | **partially applied 1A.1** (status + authority) · behavior **queued 1B** — deliberately not faked |

---

## CR-008 — Storage-driven configurable retention

| Field | Detail |
|---|---|
| Previous rule | Retention was an unanswered question (a one-year default had been floated). |
| Approved rule | **Q29** — no fixed retention period. Monitor real storage consumption, estimate remaining runway, warn before capacity, and run an approved cleanup with export-before-deletion. **No automatic destructive deletion** until the numeric parameters are approved. |
| BRD sections | §20 (non-functional), new operations section required |
| ERD entities | proposed `storage_metrics`, `retention_policies`, `cleanup_runs`; `audit_logs` for cleanup events |
| Frontend screens | admin-dashboard, system-settings |
| Backend impact | monitoring command, threshold configuration, retention report, Admin warnings |
| Migration impact | queued (no tables created in 1A.1) |
| Test impact | threshold warnings, runway estimation, refusal to delete without approved parameters |
| Status | **queued** — numeric thresholds are deployment/SRS configuration values (see OPEN-DECISIONS.md) |

---

## Corrections applied to the existing foundation

| Item | Was | Now |
|---|---|---|
| `TaskPolicy` docblock | "Manager: full operational control" | Q21 — Manager may **not** assign employees or set stage dates |
| `TaskPolicy` docblock | implied TL **and Manager** see first view | Q20 — effective TL **only** |
| `ProjectStatus` enum | 3 cases | 4 cases (`on_hold`, CR-007) |
| `users` migration note | "Q24 pending_email is OPEN — not added" | Q24 approved → `pending_email` added |
| `Department::effectiveLeader()` | omitted, "depends on open decisions" | implemented — the single authority for department leadership |
| `ProjectPolicy::complete/cancel` | returned `false` (pending Q20) | Q22 authority implemented (Manager **or** creator) |
| `User::isViewOnlyLeader()` | asked whether the user still held an active assignment — never true for the primary TL, wrongly true for employees | resolves through `Department::effectiveLeader()`: primary leader **and** somebody else effective (defect fix, 1A.1 correction pass) |
| "currently active" leadership | defined three different ways, date window ignored | one `scopeCurrentlyActive()` used by every leadership method |
| Temporary-TL administration | service reachable with no authorization layer | Manager-only policy + route middleware + FormRequest, 403-tested |
| `PolicyFoundationTest` project transitions | asserted nobody may complete/cancel (written while Q22 was open) | asserts the approved Q22 rule: Manager ✅, original creator ✅, unrelated TL ❌, Employee ❌ — found by the first real test run |
