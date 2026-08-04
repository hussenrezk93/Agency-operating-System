# Agency OS — SRS Impact Notes (Phase 1A.1)

What the approved decision register changes for the SRS, so the specification and the
code stay in step. Section numbers refer to `Agency OS_BRD_AR_v1.1_Final_Corrected.pdf`.

---

## 1. Roles (BRD §5) — rewrite required
The role model is no longer purely static. Add:

- **Effective role vs substantive role.** During a temporary-TL period an Employee's
  effective role is Team Leader while their substantive role remains Employee (Q2).
  Every permission check must resolve the *effective* role, and every report or export
  must state which one it uses.
- **Effective leader of a department** = active temporary TL if present, otherwise the
  primary TL (Q11/Q21). The SRS must name this resolution once and reference it everywhere.
- **View-only leader.** A primary TL covered by a temporary TL retains read access and
  loses every leadership action (Q14).

## 2. Temporary Team Leader (BRD §12) — substantial rewrite
Specify: eligibility (Employee, same department, active, not already covering — Q2/Q3/Q6);
mandatory reason limited to primary-TL leave (Q9); future-dated scheduling with automatic
activation and expiry (Q13); early termination (Q8); mid-period replacement (Q16);
inbound transfer of the primary TL's self-assigned tasks (Q10); retention of the elevated
employee's own tasks (Q4/Q5); hand-back of pending reviews (Q7/Q17); the prohibition on
department-membership changes by the temporary TL (Q18); and the rule that leave cannot
start without a valid temporary TL (Q15).

## 3. Task assignment (BRD §9, §15) — clarification with teeth
State explicitly that the **Manager may not** select the assignee or set/change stage
dates; that authority belongs solely to the effective TL of the receiving department (Q21).
The Manager's operational powers (hold, redirect, cancel, review of self-assigned TL work)
remain. Adding an employee to a department stays Manager-only.

## 4. Automatic Seen (BRD §9.4, §16.2, §22.8) — behavioral change
Replace "the system records the first time the employee opens the task" with the
My-Tasks-page trigger (Q19), including the idempotency and exclusion rules. Add the
visibility restriction: `first_seen_at` is shown to the **effective TL only** — not the
employee, not the Manager, not other TLs — while the event still reaches the audit log (Q20).
Remove any implication of a Seen/Acknowledge button from the UI specification.

## 5. Projects (BRD §7.2, §10, §11, §17, §21, §22) — status vocabulary change
Add **On Hold** to the project lifecycle (Q23) with: mandatory reason, pausing of
unfinished tasks, prohibition on creating new tasks, timing pause under the approved hold
accounting, unchanged history, resume restoring prior states, and completed/cancelled
tasks untouched. Also record that complete/cancel authority includes the original creator
(Q22), and that cancellation cascades to unfinished tasks, freezes the WhatsApp link and
stops invitations.

## 6. Personal email (BRD §18.1) — flow change
Specify the pending-email model (Q24): the verified address stays active and keeps
receiving notifications; the new address is pending until verified; promotion happens only
on success; expiry follows the verification policy; both the request and the successful
change are audited.

## 7. Outputs (BRD §13, §15) — clarification
All output links in an approved submission are final (Q25) — not only the last one.
Employees see only final approved outputs of previous completed steps of the same task,
and never drafts, rejected, superseded or unrelated outputs, nor another stage's private
comments (Q26).

## 8. Email infrastructure (BRD §11.1, §20) — architectural constraint
Provider-agnostic SMTP configuration; no provider name in domain services; secrets in
environment/deployment configuration only (Q27). All email flows through a queue with
retries, backoff, failed-job storage, delivery records and idempotent jobs; delivery
failure never blocks a workflow transition, and the in-system notification remains the
official channel (Q28).

## 9. Retention (BRD §20) — replaces any fixed-period rule
Storage-driven model (Q29): monitoring, configurable warning and critical thresholds,
retention reporting, estimated remaining runway, safety margin, Admin-visible warnings,
audited cleanup, and export before major deletion. Automatic destructive deletion is
prohibited until the numeric parameters are approved. The SRS should carry these as a
configuration table, not as fixed prose.

## 10. UI (new SRS section) — delivery gate
A bilingual UI Kit gallery (EN/AR, LTR/RTL, desktop/mobile) covering inputs, selects,
textareas, validation states, buttons, modals, tables, pagination, search and filters,
status badges, workflow statuses, deadline statuses, empty/loading/error states,
notifications, confirmations and accessibility focus states must be **approved before**
integration of the 51 screens begins (Q30).

---

## Acceptance criteria to add or amend

| # | Criterion | Source |
|---|---|---|
| AC-T1 | An Employee appointed Temporary TL performs TL actions for their department only, and reverts automatically at period end. | Q2, Q3, Q13 |
| AC-T2 | The original role is always recoverable from history; no transition is untraceable. | Q2 |
| AC-T3 | A department never has two effective leaders, nor zero, at any instant — including during a replacement. | Q16 |
| AC-T4 | A primary TL on leave can sign in and read, but every leadership action is refused. | Q14 |
| AC-S1 | Opening My Tasks sets `first_seen_at` exactly once per stage; reloading changes nothing. | Q19 |
| AC-S2 | `first_seen_at` is never exposed to the employee, the Manager, or another department's TL. | Q20 |
| AC-A1 | A Manager attempting to assign an employee to a stage is refused. | Q21 |
| AC-P1 | Cancelling a project cancels every unfinished task inside it, in one transaction, and cannot be reversed. | Q22 |
| AC-P2 | A project on hold accepts no new tasks and pauses its unfinished tasks — verifiable in data, not only in the UI. | Q23 |
| AC-E1 | Changing a verified email leaves notifications flowing to the old address until the new one is verified. | Q24 |
| AC-O1 | Every link in an approved submission is marked final. | Q25 |
| AC-M1 | A mail outage delays no task transition and loses no notification. | Q28 |
