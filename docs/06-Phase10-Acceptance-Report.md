# 06 — Phase 10 Acceptance Report

Status: **in progress** — 4 of 5 planned BRD-comparison audit sections are back and every
finding from them has been fixed and tested; the 5th (Users/Departments/Security) is still
running. This report will be updated to "final" once it lands. Everything below it is
otherwise complete and current as of this commit.

---

## 1. Scope

Phase 10 per `docs/04-Development-Roadmap-Phases-AR.md`: complete the audit log, close
IDOR/mass-assignment/authorization gaps, add rate limiting, run every automated test,
review mobile/tablet/RTL, do a final BRD/ERD comparison, and produce deployment
documentation and this acceptance report. No new features were in scope going in — but the
BRD comparison surfaced several BRD-specified behaviors that had never actually been built,
so those were built too (see §3).

## 2. Security hardening

| Item | Result |
|---|---|
| Audit log completeness | Found and closed 3 gaps: the department-routing and output-access admin matrices wrote directly from their controllers with no audit trail; `TaskWorkflowService::addOutput()`/`supersedeOutput()` updated the task timeline but never the security audit log. All three now go through the established service-layer-writes-then-audits pattern. |
| IDOR / mass-assignment sweep | Checked every controller for either a `FormRequest::authorize()` → `can()` check, a Policy-level `authorize()` call, or route-level `role:` middleware. Confirmed no controller passes a raw request array into `create()`/`update()`/`fill()` — every mutation builds an explicit attribute list. No gaps found beyond the audit-log ones above. |
| Rate limiting | Login already had custom brute-force throttling (`AuthService`, keyed by username+IP, with its own audit trail). Added throttling to the two remaining reachable-without-full-auth endpoints: the email-verification link (defense-in-depth on top of its 40-char token) and the resend-verification action. |
| Session security | Already correct — session regenerates on login (fixation protection) and token regenerates on logout. No change needed. |
| CSRF | Laravel default, already on for every state-changing route. No change needed. |

## 3. BRD-vs-implementation audit

Five parallel sub-audits were run, each covering a slice of the BRD against the live
codebase (not the roadmap doc's own stale "queued" labels, which were checked against
actual `database/migrations/` and confirmed out of date — all 10 phases are in fact built).

**Sections back: Task workflow/outputs (§8,9,10,13,15,22), Clients/Projects/WhatsApp
(§7.x), Permissions/Dashboards/Reports (§15-17,20-22), Deadlines/Notifications/Chat
(§11,11.1,12,14,20,22).**
**Section pending: Users/Departments/Security.**

Every genuine gap found in the four returned sections was fixed and covered by a new
regression test in the same commit:

1. Project cancel didn't cascade to cancel its unfinished tasks (BAC#10) — now mirrors
   the existing hold() cascade.
2. Reference links weren't actually required on task creation despite BRD §8 saying "one
   or more" — now enforced, with the create form updated to match.
3. Deadline-only step edits forced a reason, contradicting BRD §11 — now only an actual
   employee change requires one.
4. No task-edit endpoint existed at all — `TaskPolicy::update()` was defined but dead
   code. Built `GET/PATCH /tasks/{task}/edit`.
5. Draft tasks (BRD §8) weren't implemented — the `draft` lifecycle state and nullable
   `current_step_id` were already in the schema from Phase 2, unused. Built save/publish/
   delete.
6. The §15 Admin-configured cross-department output-visibility matrix
   (`DepartmentOutputAccess`) was a fully built, fully tested CRUD screen that nothing
   ever consulted — any TL who could see a task saw every department's output regardless
   of what Admin had configured. Now wired into the actual visibility check.
7. §7.3's WhatsApp fan-out/alert triggers on department membership changes (new
   employee, moved employee, disabled user, department removed from a project) never
   fired. Built all four, and found a real bug while testing the first one (an
   enum-vs-string comparison that silently no-opped the guard clause).
8. Inline client creation on the project-create form existed only in the static
   prototype. Wired to the real form/controller.
9. Two named-but-missing dashboard widgets: Manager's "redirected, still awaiting
   assignment" list (BRD §16.3), TL's per-employee first-view-time indicator (BRD §16.2,
   data already existed, just wasn't surfaced there).
10. General notification emails batch every 3 hours instead of sending immediately,
    which is a real BRD §11.1 deviation — but a deliberate, already-tested one with no
    formal approval on record. Retroactively documented as CR-009 in
    `CHANGE-REQUEST-REGISTER.md` rather than reverted (user decision).

A full RTL/LTR sweep (prompted by, but broader than, the Phase 10 checklist item) found 4
more pre-existing spots using `margin-left`/`margin-right` instead of the logical
properties (`margin-inline-start`/`-end`) the rest of the CSS already uses consistently —
fixed.

## 4. Test suite

606 tests passing, 0 failing, as of this report. `./vendor/bin/pint` clean. Every fix in
§2 and §3 shipped with at least one new regression test in the same commit — see
`git log` for the batch-by-batch breakdown (7 batches, each independently tested and
committed).

## 5. Deployment readiness

`docs/05-Deployment-Operations-Guide.md` covers setup, required `.env` values, the two
long-running processes (queue worker + scheduler) and everything currently scheduled,
backup/restore, health checks, and SPF/DKIM/DMARC. `README-BACKEND.md` (Phase 1A-era) is
flagged as historical and superseded.

The project now has a git history (initialized this phase) — every change in this report
is an individually reviewable, revertable commit.

## 6. What's NOT done

- **Users/Departments/Security audit section** — still running; this report will be
  amended once it returns, and any gap it finds will be fixed the same way as §3's.
- **Mobile/tablet testing in an actual browser** — not possible from this environment (no
  browser/screenshot tool available). The responsive CSS was reviewed, not visually
  verified.
- **A formal WCAG accessibility audit** — the UI follows sensible contrast/focus-state
  practice throughout but was not independently checked against WCAG criteria.
- **Load/performance testing at scale** and **infrastructure provisioning** — out of this
  application's scope; see the deployment guide's own "not covered" section.
- Deferred pieces of the earlier UI/UX redesign request (per-page icon replacement beyond
  the shared shell, dark mode, skeleton loaders) — explicitly out of scope by the user's
  own decision, not a Phase 10 gap.

## 7. Approval criteria (roadmap §00 checklist)

| Criterion | Status |
|---|---|
| All tests pass | ✅ 606/606 |
| No known critical vulnerabilities | ✅ per §2's sweep |
| Final comparison against BRD/ERD | ⏳ 4/5 sections done, all findings fixed; 1 pending |

**This report is not yet final sign-off** — pending the 5th audit section.
