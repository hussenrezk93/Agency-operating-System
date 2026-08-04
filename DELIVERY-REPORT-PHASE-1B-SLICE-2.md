# Agency OS — Phase 1B Slice 2 Delivery Report

**Scope:** Task Workflow Engine + Authorization Logic + Automated Tests
**Baseline:** BRD v1.1 Final Corrected · ERD v1.2 · approved decisions Q1–Q30 · CR-001…CR-008
**Date:** 30 July 2026

---

## 0. Test results — READ THIS FIRST

**The suite has NOT been executed. It cannot be, in the environment this code was written in:
there is no PHP, no Composer, no PostgreSQL, and no network egress.**

Everything below is **statically verified only**:

| Check | Result |
|---|---|
| Brace / paren / bracket balance, all 32 files | ✅ balanced |
| One `<?php` per file, no closing tag, trailing newline | ✅ |
| PSR-4: namespace ↔ directory, class ↔ filename | ✅ |
| Unused imports | ✅ none |
| Every framework API used confirmed present in `vendor/` (`Authorizable`, `AuthorizesRequests`, `Rule::enum`, `Rule::requiredIf`) | ✅ |
| Every model method called confirmed present in Phase 1A/1B-slice-1 source | ✅ |
| `Builder::aggregate()` clears `orderBy` — so `$task->steps()->max('sequence_no')` is safe despite the relation's ordering, which PostgreSQL would otherwise reject | ✅ verified in framework source |

**Required next action by the development team:**

```bash
php artisan migrate:fresh --seed     # no new migrations; proves nothing regressed
php artisan test
./vendor/bin/pint                    # style pass
```

Report failures back and they will be fixed. This is exactly the process that caught the two
real defects in the 1A.1 pass, and no claim of a green suite should be accepted without it.

**New test inventory: 5 classes / 90 test methods.** PHPUnit will report a higher executed
count than 90 only if data providers are added later; none are used here.

---

## 1. Files created (26)

### Domain services
| File | Purpose |
|---|---|
| `app/Services/TaskWorkflowService.php` | The state machine. Sole writer of `workflow_status`, `lifecycle_status`, assignments, outputs, reviews and `task_status_history`. |
| `app/Services/TaskRoutingService.php` | `canTransferToDepartment()`, `assertTransferAllowed()`, `allowedNextDepartments()`. |

### Authorization
| File | Purpose |
|---|---|
| `app/Policies/TaskStepPolicy.php` | Step-level authority: assign, addOutput, submit, review, approve, requestChanges, transfer, markSeen, viewFirstSeen, viewComments, view. |

### Exceptions
| File | Purpose |
|---|---|
| `app/Exceptions/WorkflowException.php` | Base; renders 422 with `error: workflow_violation`. |
| `app/Exceptions/IllegalTransitionException.php` | Wrong state for the requested move. |
| `app/Exceptions/MissingOutputException.php` | Submission with no output in the current round. |
| `app/Exceptions/RoutingNotAllowedException.php` | Hop not permitted by the matrix / project. |

### Events (dispatched, no listeners — the Phase 7 seam)
`TaskStepAssigned` · `TaskStepFirstSeen` · `TaskStepSubmitted` · `TaskStepReviewed` ·
`TaskStepTransferred` · `TaskCompleted`

### Form Requests
`StoreTaskRequest` · `AssignTaskStepRequest` · `AddTaskOutputRequest` ·
`ReviewTaskStepRequest` · `TransferTaskStepRequest` · `CancelTaskRequest`

### Controllers (thin — no business logic)
`TaskController` · `TaskStepController` · `MyTasksController`

### Tests
| File | Methods |
|---|---|
| `tests/Feature/TaskWorkflowTest.php` | 34 |
| `tests/Feature/TaskAuthorizationTest.php` | 25 |
| `tests/Feature/TaskSeenTest.php` | 11 |
| `tests/Feature/TaskRoutingTest.php` | 11 |
| `tests/Unit/WorkflowStatusTransitionTest.php` | 9 |
| `tests/Concerns/BuildsWorkflowScenarios.php` | scenario builder trait |

---

## 2. Files modified (4)

| File | Change | Risk to the existing 124 tests |
|---|---|---|
| `app/Enums/WorkflowStatus.php` | **Added** `allowedNextStatuses()` and `canTransitionTo()`. No case added, renamed or removed. `isTerminal()` and `isEditableByAssignee()` untouched. | None — purely additive. A regression test pins the two existing helpers. |
| `app/Policies/TaskPolicy.php` | **Replaced.** The `before() => false` placeholder is gone; real rules implemented. | None — no existing test asserted the placeholder's behaviour. |
| `app/Providers/AppServiceProvider.php` | Registered `TaskPolicy` for `Task` and `TaskStepPolicy` for `TaskStep`. Existing five registrations unchanged. | None. |
| `routes/web.php` | Added 14 workflow routes. Existing routes, names and middleware untouched, including the four role-spine smoke routes `RoleMiddlewareTest` depends on. | None — verify `RoleMiddlewareTest` still passes. |

---

## 3. Database changes

**NONE.** No migration was added, altered or removed. No column, index, constraint or enum
value was introduced. The engine was built entirely against the slice-1 schema, which is why
`migrate:fresh` should behave exactly as it did before.

Slice-1 columns that this slice starts writing for the first time: `task_steps.workflow_status`,
`current_start_date`, `current_due_at`, `submitted_at`, `approved_at`, `completed_at`,
`deadline_status`; `task_step_assignments.*` including `first_seen_at` and `is_self_assigned`;
`task_step_outputs.submission_no` / `is_final`; `task_step_reviews.*`; `task_status_history.*`;
`tasks.lifecycle_status`, `current_step_id`, `completed_*`, `cancelled_*`.

---

## 4. Workflow states implemented

### Vocabulary reconciliation (approved decision Q6 — the ERD is authoritative)

The brief's status names are not the ones in the database. They were **mapped, not migrated**:

| Brief | Implemented as |
|---|---|
| Created | `tasks.lifecycle_status = active`, step 1 opened |
| Assigned | `workflow_status = waiting_assignment` → `in_progress` on assignment |
| Submitted | **not a separate state** — `under_review`, with `submitted_at` recording the moment |
| Under Review | `under_review` |
| Changes Requested | `changes_requested` |
| Approved | `approved` (terminal for the step) |
| Next Department | **not a status** — a new `task_steps` row; the finished step stays `approved` |
| Completed | `tasks.lifecycle_status = completed` — never a step status |

### The transition map (`WorkflowStatus::allowedNextStatuses()`)

```
waiting_assignment → in_progress | redirected | cancelled
in_progress        → under_review | redirected | cancelled
under_review       → approved | changes_requested | redirected | cancelled
changes_requested  → under_review | redirected | cancelled
approved           → (terminal)
redirected         → (terminal)
cancelled          → (terminal)
```

### The forbidden moves in the brief

`In Progress → Completed`, `Created → Completed` and `Submitted → Completed` are **not
expressible as step transitions at all**, because Completed is not a step status. They are
blocked in `completeTask()`, which requires the step be `approved` **and** carry the highest
`sequence_no` on the task. Both layers are tested — the map in the unit test, the guard in
four feature tests.

### Full lifecycle covered

create → open step 1 (Waiting Assignment) → assign (or self-assign) → automatic Seen →
add output(s) → submit → review → approve **or** request changes → resubmit → send to next
department **or** finish task. Plus reassignment, task cancellation with step cascade, and
output supersession.

---

## 5. Permission rules implemented

`TaskPolicy` (task-level) and `TaskStepPolicy` (step-level) together contain every permission
named in the brief. They are split by what the action targets, because assign/submit/review
act on one department's turn, not on the whole task.

| | Admin | Manager | Effective TL | Employee |
|---|---|---|---|---|
| view task / step | ✗ | ✓ all | ✓ own department | ✓ assigned only |
| create task | ✗ | ✓ | ✓ | ✗ |
| **assign / reassign** | ✗ | **✗ (Q21)** | ✓ own department | ✗ |
| add output | ✗ | ✗ | ✓ if self-assigned | ✓ current assignee |
| submit | ✗ | ✗ | ✓ if self-assigned | ✓ current assignee |
| review / approve / request changes | ✗ | **✓ only when self-assigned (Q12)** | ✓ unless self-assigned | ✗ |
| transfer | ✗ | ✗ (Redirect is theirs) | ✓ own department | ✗ |
| complete task | ✗ | ✓ | ✓ holder of final step | ✗ |
| cancel task | ✗ | ✓ | ✓ if creator | ✗ |
| read `first_seen_at` | ✗ | **✗ (Q20)** | ✓ own department only | **✗ (Q20)** |

Leadership is **always** resolved through `User::canActAsLeaderOf()` →
`Department::effectiveLeader()`, never `users.role`. A temporary Team Leader therefore has the
full operational set (Q11) and a covered primary Team Leader has none of it (Q14).

### Three enforcement layers, one definition

1. **Route middleware** — rejects roles that can never perform the action. Note who is absent:
   `role:tl` on assign/reassign/transfer excludes the Manager (Q21).
2. **`FormRequest::authorize()`** — refuses before validation runs.
3. **`TaskWorkflowService`** — calls the same policy via `Gate::forUser()`, so no console
   command, queue job or seeder can reach a transition unauthorized.

The rule is written **once**, in the policy, and enforced at three boundaries. That is not
duplication.

### Policies do not repeat the state machine

Fine-grained status preconditions (transfer needs Approved, review needs Under Review) live
**only** in the service. Two reasons: a precondition written twice drifts, and it produces
wrong HTTP semantics — "you may not do this" is 403, "not from this state" is 422. Policies
check only the coarse question of whether the step is still live at all.

---

## 6. Decisions requiring your confirmation

These are places where the brief, the BRD and the approved register did not fully determine
the answer. Each is implemented as stated and each is reversible.

1. **Manager may `complete()` a task.** Without it, a self-assigned TL step approved by a
   Manager under Q12 is left approved with nobody authorized to finish it.
2. **Manager may not `transfer()`.** Their route-correction tool is Redirect (BRD §10) with its
   mandatory reason and separate audit trail.
3. **Project tasks route only to participating departments.** Not a BRD rule. Added because
   project membership — and therefore WhatsApp invite eligibility (CR-001) and project
   visibility (BRD §7.2) — derives from the participating departments.
4. **`reassign` leaves `workflow_status` unchanged.** Changing who works on a step is not a
   review-cycle transition; a step in Changes Requested stays there.
5. **Submitting requires an output in the *current* round.** Links carried over from a rejected
   round do not satisfy it — otherwise Request Changes could be closed by resubmitting
   untouched work.
6. **An on-hold task accepts no workflow action, including cancellation.** Conservative; revisit
   with the hold service in the next slice.
7. **Outputs are links only.** The brief mentions files; BRD §4.2 puts file storage out of scope
   and no column exists.
8. **Routing matrix stays Admin-managed.** The brief called it Manager-controlled; BRD §15 says
   Admin. Implemented read-only against `department_routes`. Moving that authority is a CR.

---

## 7. Deliberately NOT in this slice

Hold / resume deadline accounting (Q5/Q9/Q23) · Manager Redirect (BRD §10) · the deadline
engine that computes Due Soon and Overdue · project cancellation cascade and WhatsApp freeze
(Q22) · temporary-TL task transfer (Q10) and review hand-back (Q7/Q17) · notifications and
email (Q27/Q28) · chat · performance scoring and reports · all Blade screens (gated by the Q30
UI Kit checkpoint) · draft/publish for tasks (BRD §8).

`deadline_status` is moved only Not Started → On Time → Closed. `DeadlineService` remains its
owner and does not exist yet. Nothing above is stubbed as if working.

---

## 8. Suggested approval command

Only after `php artisan test` passes locally:

```
Phase 1B Slice 2 is approved. Start Phase 1B Slice 3 (hold/resume + redirect + deadline
engine) only. Do not modify approved files unless required, and return every modified file
in full.
```
