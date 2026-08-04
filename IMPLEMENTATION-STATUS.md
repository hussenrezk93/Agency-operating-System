# Agency OS — Implementation Status (Phase 1B Slice 1 + bilingual UI foundation)

Updated: 27 July 2026 · Baseline: **BRD v1.1 Final Corrected + ERD v1.2 + approved Q1–Q30**
**Phase 1B Slice 1 schema is delivered; workflow services remain deferred to Slice 2.**

---


## Bilingual UI foundation update

- Added a persistent Arabic/English language switch available before and after login.
- Arabic renders the shared Blade layout as RTL; English renders it as LTR.
- Added localized foundation screens for login, forced-password change and dashboard.
- Added locale-aware mock users, departments, clients, projects, tasks, statuses and priorities.
- Added an orange + white primary design-token system for the current views and future Tailwind components.
- Added `SetLocale`, `LocaleController`, `LocalizedDemoData` and `LocalizationTest`.
- New installations default to Arabic through `.env.example`; the user's selected language persists in session and cookie.

## Completed

### Defect fixes (this pass)
- **`User::isViewOnlyLeader()` — corrected.** The old logic asked whether the user still
  held an active leadership assignment; a primary TL keeps that assignment throughout the
  leave, so the check never fired for the primary TL and wrongly fired for ordinary
  department members. View-only is now derived from leadership resolution: *the user is the
  primary leader **and** somebody else is the effective leader*.
- **One definition of "currently active".** New
  `DepartmentLeadershipAssignment::scopeCurrentlyActive()` — valid + activated + today
  inside the date window (Africa/Cairo). `primaryLeader()`, `temporaryLeader()`,
  `effectiveLeader()`, `activeLeadershipAssignment()`, `hasActiveTemporaryLeader()`,
  `leadsDepartment()` and `isEffective()` all use it. The date window also protects against
  a late scheduler: an expired temporary leader loses authority on the correct day.
- **`Department::temporaryLeader()` / `temporaryLeadershipAssignment()`** added.
- **`User::canActAsLeaderOf()`** added — leads **and** not view-only, the check policies use.
- **`User::hasLeadershipAssignment()`** added to keep "holds an assignment" separable from
  "may act", so the defect cannot recur.

### Manager-only authorization for temporary-TL administration
`TemporaryLeadershipPolicy` (appoint / replace / endEarly / viewAny / manageMembership),
`AppointTemporaryLeaderRequest`, `ReplaceTemporaryLeaderRequest`,
`TemporaryLeadershipController`, and four routes behind `role:manager`.
**Three independent layers**: route middleware → FormRequest::authorize() → controller
policy call. The service is not reachable through any endpoint without passing all three.
Base `Controller` now uses `AuthorizesRequests` + `ValidatesRequests`.

### Complete Laravel application (was an overlay)
`artisan`, `public/index.php`, `public/.htaccess`, `public/robots.txt`,
`bootstrap/providers.php`, storage + bootstrap cache placeholders with `.gitignore`,
`config/session|cache|queue|mail|logging|filesystems.php`, `lang/en` + `lang/ar`,
`pint.json`, framework cache/queue tables migration, composer scripts.

### Model relationships completed
`User`: role, baseRole, department, leadershipAssignments, primaryLeadershipAssignments,
temporaryLeadershipAssignments, roleTransitions, createdClients, createdProjects,
auditEntries. `Department`: users, leadershipAssignments, primaryLeader, temporaryLeader,
effectiveLeader, routesFrom, routesTo, outputAccessAsViewer, outputAccessAsSource, projects.
`DepartmentLeadershipAssignment`: department, user, assignedBy, endedBy,
replacedByAssignment, roleTransitions. `Client`: creator, projects.
`Project`: client, creator, departments, links, completedBy, cancelledBy,
whatsappLinkUpdatedBy. `ProjectLink`: project, addedBy. `AuditLog`: actor.

### Documentation cleaned
Stale text removed from `UserStatus` ("OPEN (Q14)"), `LeadershipType` ("open Phase 0
decisions"), the users migration ("Q24 is OPEN") and `config/auth.php` ("open decision
(Q27)"). Zero occurrences of *is OPEN*, *unanswered*, *open Phase 0* or *open decision*
remain in code. The four remaining `TODO(Phase 1B · Q…)` markers are legitimate deferrals
inside `TemporaryLeadershipService` for the task-transfer hooks.

### Tests
**14 classes · 97 methods** (was 13 / 75). New `TemporaryLeadershipAuthorizationTest` (9)
covering Manager-can / Admin-TL-Employee-cannot / guest-blocked / direct-endpoint / policy
level / validation. `TemporaryLeadershipTest` rewritten to 18 methods covering all 20
required scenarios using **Carbon time travel**, never the real clock.
`LoginRateLimitTest` +3 (separate counters per username and per IP, no password in audit).
`AuditLogPolicyTest` +1 (metadata contains no secrets).

---

## Runtime verified ✅

The suite was executed by the development team on **PostgreSQL** (Windows, local
environment) after the setup fixes in `WINDOWS-SETUP.md`.

| Step | Result |
|---|---|
| `php artisan migrate:fresh --seed` | ✅ succeeded — all 15 migrations applied, including the `btree_gist` EXCLUDE constraint and the append-only audit trigger |
| `php artisan test` | ✅ **124 passed, 0 failed, 0 skipped** |
| Database driver | PostgreSQL (the `DatabaseGuardServiceProvider` refuses anything else) |

Test inventory: **14 classes / 102 test methods**, which PHPUnit expands to **124 executed
tests** because data providers count each case separately (`RoleMiddlewareTest::matrix()`
runs 16 role × route combinations; `TemporaryLeadershipAuthorizationTest::unauthorizedRoles()`
runs 3 unauthorized roles across 3 endpoints).

Both defects listed below were found by real execution, fixed, and the full suite re-run
green. Exact assertion count and duration were not captured in the report.

### Defects found by running the suite

**1 — Enum casts hid three database constraints from their own tests.**
`OrganizationStructureTest` asserted that PostgreSQL rejects `status = 'sabbatical'`,
`status = 'archived'` and `scope = 'everything'`. It never did: the Eloquent enum cast
(`'status' => UserStatus::class`) converts the value *before* the query is built, so
`UserStatus::from()` threw `ValueError` and the statement never reached the database.
The CHECK constraints were therefore untested, and the tests failed asserting the wrong
exception type.

**Corrected:** each case is now two tests, matching the two real layers of defence —
the enum cast rejecting it in the application (`ValueError`), and the CHECK constraint
rejecting it in PostgreSQL when Eloquent is bypassed via `DB::table()` (`QueryException`),
which is the path a raw import, a manual `psql` session or a future queue job would take.
A sweep confirmed no other test passes an invalid enum literal through Eloquent.

**2 — A stale policy test contradicted an implemented decision.**

`PolicyFoundationTest::test_deferred_project_transitions_are_denied_to_everyone_for_now`
asserted that **nobody** could complete or cancel a project. That expectation was written
during Phase 1A while Q22 was still unanswered and `ProjectPolicy::complete()` returned
`false`. Phase 1A.1 implemented the approved Q22 authority (Manager **or** original
creator) but the stale test was not updated with it, so the suite contradicted the policy.

**Corrected:** the test now asserts the approved rule — Manager ✅, original creator ✅
(including a Team Leader creator), unrelated Team Leader ❌, Employee ❌ — proving creator
authority is personal and never inherited by role. Two coverage gaps it exposed were also
closed: a completed or cancelled project is read-only for everyone forever, and Hold/Resume
(Q23) carry the same authority as Complete/Cancel.

> ✅ Confirmed: the full suite was re-run after both corrections — **124 passed**.

## Statically verified (performed in the authoring environment)

| Check | Result |
|---|---|
| PHP source structure, headers, delimiter balance | ✅ 0 issues |
| Class ↔ file name (PSR-4), duplicate classes/migrations | ✅ none |
| `composer.json` / `pint.json` JSON, `phpunit.xml` XML | ✅ valid |
| Migration FK ordering (15 migrations, 18 tables) | ✅ no forward references |
| Route names ↔ tests · policies registered | ✅ 14 routes · 5 policies |
| Stale-marker scan (Q14/Q24/unanswered/Phase 0/open decision) | ✅ 0 in code |

## Phase 1B — slice 1 delivered (task schema)

**Schema (4 migrations, 11 tables):** `tasks`, `task_reference_links`, `task_steps`,
`task_step_assignments`, `task_step_outputs`, `task_step_comments`, `task_step_reviews`,
`project_holds`, `task_holds`, `task_redirects`, `task_status_history`.

**Approved-decision amendments applied**
- **Q8** — the authoritative deadline lives on the STEP (`current_start_date`,
  `current_due_at` at 23:59 Africa/Cairo), so reassigning an employee cannot silently move
  the department's commitment; the assignment keeps its own dates for history.
- **Q25** — outputs carry `submission_no` and `superseded_by_output_id`, because "every
  link in an approved submission is final" cannot be derived from "the latest row".
- **Q23** — `project_holds` is a real table, and a project hold writes a child `task_holds`
  row per unfinished task (`project_hold_id`), so the deadline engine keeps ONE accounting
  path and resuming restores exactly what was paused. *This was my recommendation, applied
  by decision; it is reversible until the hold service ships in slice 3.*
- **Q5/Q9** — `task_holds.paused_seconds` is stored on resume, so the deadline extension is
  a recorded fact rather than a recomputation.
- **Q12** — `task_step_assignments.is_self_assigned` marks the steps a Manager must review.
- **Q19** — `first_seen_at` exists on the assignment, nullable, written once.

**Database-enforced invariants added:** one open assignment per step · one open hold per
task and per project · unique `sequence_no` per task · `due_date >= start_date` ·
valid workflow/deadline/priority/lifecycle vocabularies · mandatory comment on
`changes_requested` · mandatory reason on cancel/hold/redirect · completed tasks must
record who and when · an output cannot supersede itself · a redirect cannot target its own
step.

**Enums:** `TaskLifecycle`, `WorkflowStatus`, `DeadlineStatus`, `Priority`,
`ReviewDecision`, `TaskEvent`.
**Models:** 11 new, plus `Project`/`Department`/`User` wired to the task domain.
**Tests:** `TaskSchemaTest` — 21 methods covering every constraint above.

> ⚠️ Slice 1 is **schema only**. No transition logic ships here: `TaskPolicy` is still
> deny-by-default and `TaskWorkflowService` does not exist yet. That is slice 2.

## Deliberately deferred (Phase 1B)

Task tables and workflow state machine · assignment engine · automatic Seen (Q19) and its
TL-only visibility (Q20) · output submission and final-output grouping (Q25/Q26) · TL review
and Manager review of self-assigned TL work (Q12) · request changes · department transfer ·
hold/resume accounting · task cancellation · project cancellation cascade and WhatsApp
freeze (Q22) · project Hold pausing behavior (Q23 — status and authority exist, pausing does
not) · temporary-TL **task transfer** (Q10) and review hand-back (Q7/Q17) · notifications ·
mail module and queue jobs (Q27/Q28) · chat · WhatsApp versioning and invite ledger ·
performance scoring · reports · 51-screen integration (gated by the Q30 UI Kit checkpoint) ·
storage/retention module (CR-008).

**None of these is stubbed as if working.** The corresponding tables do not exist.

## Remaining environment blockers

**None for the development team** — PostgreSQL is installed and the suite runs.

The *authoring* environment used to produce this package still has no PHP, Composer or
PostgreSQL and no network egress, so every change made here is statically verified only
and must be confirmed by running the suite locally. That is precisely how the stale
project-policy test above was caught.
