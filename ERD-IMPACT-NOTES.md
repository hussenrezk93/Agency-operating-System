# Agency OS — ERD Impact Notes (Phase 1A.1)

Baseline: corrected **ERD v1.2**. Everything below is either an amendment required by the
approved decision register, or a note for the Phase 1B modeller. Amendments marked
**APPLIED** exist in migrations today; **PROPOSED** items are documented only.

---

## 1. `users` — APPLIED

| Column | Type | Reason |
|---|---|---|
| `base_role_id` | FK → roles, nullable | **Q2/CR-003.** `role_id` is now the *effective* role; `base_role_id` preserves the substantive role during a temporary elevation and is NULL otherwise. Prevents any destructive overwrite of the original role. |
| `pending_email` | varchar, nullable | **Q24.** The new address is held here while the verified one stays active. |
| `pending_email_requested_at` | timestamptz, nullable | **Q24.** Supports expiry of the pending verification. |

**Invariant (application-level):** `base_role_id IS NOT NULL` ⟺ an active temporary
leadership assignment exists for that user. Enforced by `RoleTransitionService`, covered by tests.

## 2. `user_role_transitions` — APPLIED (new entity)

`id, user_id, from_role_id, to_role_id, transition_type(elevation|restoration),
reason(temporary_tl_start|temporary_tl_end|temporary_tl_early_end|temporary_tl_replaced),
leadership_assignment_id?, performed_by?(null = scheduler), effective_at, created_at`

**Q2** requires history rather than an untraceable overwrite. `performed_by` is nullable
because automatic transitions (Q13) have no human actor. Written only by
`RoleTransitionService`; never edited.

## 3. `department_leadership_assignments` — APPLIED (extended, not recreated)

| Column | Reason |
|---|---|
| `activation_state` (`pending`\|`active`\|`ended`) | **Q13** — future-dated assignments activate and expire automatically. `is_active` remains the *validity* flag; `activation_state` is the *lifecycle* position. Both are needed: a pending assignment is valid but not yet effective. |
| `reason` (text) | **Q9** — a temporary appointment exists only because the primary TL is on leave. |
| `ended_by` (FK users) | **Q8** — who cut the period short. |
| `replaced_by_assignment_id` (self-FK) | **Q16** — explicit chain when a temporary leader is swapped mid-period. |

Constraints now in place: `dla_activation_state`, `dla_type`,
`dla_temp_has_end` (a temporary appointment must be time-boxed), plus the pre-existing
`dla_one_active_primary`, `dla_one_active_led_dept`, `dla_no_temp_overlap`.

> **Note on Q3/Q6.** "The temporary leader must belong to the same department" spans two
> tables (`users.department_id` vs `department_leadership_assignments.department_id`) and is
> not expressible as a standard SQL CHECK. It is enforced in
> `TemporaryLeadershipService::assertEligible()` and covered by tests. A trigger could
> enforce it in the database later if the owner wants defence-in-depth.

## 4. `department_routes` — APPLIED (was missing from the 1A foundation)
Per ERD v1.2, plus `dr_no_self_route` (a department cannot route to itself).

## 5. `department_output_access` — APPLIED (was missing)
Per ERD v1.2, plus `doa_no_self` and a scope CHECK (`all_outputs|final_only`).
Default seeded as `all_outputs` — **Q25** keeps `final_only` supported.

## 6. `project_links` — APPLIED (was missing)
Per ERD v1.2. Links only; Agency OS stores no files in v1.

## 7. `projects.status` — APPLIED (vocabulary change)
`active | on_hold | completed | cancelled` — **Q23/CR-007** adds `on_hold`.
Also `projects_cancel_reason_check`: a cancelled project must carry a reason (**Q22**).

## 8. `project_holds` — PROPOSED (Phase 1B)
**Q23** forbids a visual-only hold, so the pause needs its own record, mirroring `task_holds`:
`id, project_id, created_by, reason, started_at, ended_at?, resumed_by?, extension_interval?`.
Open modelling question for 1B: whether project hold writes child `task_holds` rows per
paused task (clean accounting, more rows) or is resolved dynamically at read time
(fewer rows, more complex deadline maths). **Recommendation: write child rows**, so the
existing deadline engine stays the single accounting path.

## 9. `task_step_outputs` — PROPOSED refinement (Phase 1B)
**Q25** states that *all* links in an approved submission are final, not just the latest.
`is_final` therefore cannot mean "latest row". Proposal: add `submission_id` (or
`submission_no`) to group outputs per submission, and `superseded_by_output_id` for the
"add a replacement, preserve the original" rule. `is_final` is then set for the whole
approved submission group.

## 10. `task_step_assignments.first_seen_at` — no schema change
**Q19/Q20** change *when* it is written and *who* may read it, not the column.
Visibility (effective TL only) is a policy concern, not a schema one.

## 11. Storage & retention — PROPOSED (Phase 1B+, CR-008)
`storage_metrics(captured_at, entity, bytes, rows)`,
`retention_policies(entity, min_retention_days, export_before_delete, approved_by, approved_at)`,
`cleanup_runs(started_at, finished_at, entity, rows_affected, exported_to, actor_user_id)`.
No table is created until the numeric thresholds are approved.

---

## Summary

| ERD change | Kind | Status |
|---|---|---|
| `users.base_role_id`, `pending_email`, `pending_email_requested_at` | amendment | APPLIED |
| `user_role_transitions` | new entity | APPLIED |
| `department_leadership_assignments` +4 columns +3 constraints | amendment | APPLIED |
| `department_routes`, `department_output_access`, `project_links` | ERD tables previously missing | APPLIED |
| `projects.status` += `on_hold` | vocabulary | APPLIED |
| `project_holds` | new entity | PROPOSED |
| `task_step_outputs` submission grouping / supersede | amendment | PROPOSED |
| storage/retention trio | new entities | PROPOSED |
