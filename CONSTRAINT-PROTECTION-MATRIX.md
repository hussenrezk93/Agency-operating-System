# Agency OS — Invariant Protection Matrix (Phase 1A.1)

Which layer protects every confirmed invariant. "DB" means PostgreSQL rejects the write
regardless of the code path; "Service/Policy" means application enforcement with a test
proving it. No task-workflow invariants appear here — those arrive in Phase 1B.

| # | Invariant | DB | Service / Policy | Test |
|---|---|---|---|---|
| 1 | Username is unique | ✅ unique index | — | `DatabaseConstraintTest` |
| 2 | Personal email is unique | ✅ unique index | — | `DatabaseConstraintTest` |
| 3 | Valid `users.status` | ✅ `users_status_check` | enum cast | `OrganizationStructureTest` |
| 4 | Valid `clients.status` | ✅ `clients_status_check` | enum cast | — |
| 5 | Valid `projects.status` incl. `on_hold` | ✅ `projects_status_check` | enum cast | `OrganizationStructureTest` |
| 6 | Cancelled project carries a reason | ✅ `projects_cancel_reason_check` | FormRequest (1B) | `OrganizationStructureTest` |
| 7 | Valid leadership `assignment_type` | ✅ `dla_type` | enum cast | — |
| 8 | Valid `activation_state` | ✅ `dla_activation_state` | enum cast | — |
| 9 | Temporary assignment is time-boxed | ✅ `dla_temp_has_end` | service validation | — |
| 10 | Role is a valid foreign key | ✅ FK `users.role_id → roles.id` | `RoleCode` enum | `ModelRelationshipTest` |
| 11 | One active PRIMARY TL per department | ✅ partial unique `dla_one_active_primary` | `DepartmentService` transaction | `DatabaseConstraintTest` |
| 12 | No overlapping TEMPORARY periods per department | ✅ `EXCLUDE USING gist` (btree_gist) | `assertEligible()` | `TemporaryLeadershipTest` |
| 13 | One active leadership per user | ✅ partial unique `dla_one_active_led_dept` | `assertEligible()` | `TemporaryLeadershipTest` |
| 14 | **Temporary TL belongs to the same department** | ❌ not expressible in standard SQL (spans two tables) | ✅ `TemporaryLeadershipService::assertEligible()` + `AppointTemporaryLeaderRequest` | `TemporaryLeadershipTest` (rejection) |
| 15 | Only an **Employee** may be temporary TL | ❌ (depends on the effective role at the time) | ✅ `assertEligible()` | `TemporaryLeadershipTest` |
| 16 | Temporary-TL administration is Manager-only | ❌ | ✅ policy + route middleware + FormRequest (3 layers) | `TemporaryLeadershipAuthorizationTest` |
| 17 | Department cannot exist without a primary TL | ❌ (chicken-and-egg at insert time) | ✅ `DepartmentService::createWithPrimaryLeader()` in one transaction | `OrganizationStructureTest` |
| 18 | Department name unique | ✅ unique index | duplicate check in service | `DatabaseConstraintTest` |
| 19 | `project_code` unique | ✅ unique index | generator (1B) | `DatabaseConstraintTest` |
| 20 | Unique project↔department pair | ✅ unique index | — | `DatabaseConstraintTest` |
| 21 | A department cannot route to itself | ✅ `dr_no_self_route` | — | `OrganizationStructureTest` |
| 22 | Route pair unique | ✅ unique index | — | `OrganizationStructureTest` |
| 23 | Output-access scope is valid, no self-access | ✅ `doa_scope`, `doa_no_self` | enum cast | `OrganizationStructureTest` |
| 24 | Audit log is append-only | ✅ trigger blocks UPDATE/DELETE | `AuditLogPolicy` denies all writes | `AuditServiceTest`, `AuditLogPolicyTest` |
| 25 | Audit log is Admin-read-only | ❌ | ✅ `AuditLogPolicy` | `AuditLogPolicyTest` |
| 26 | Audit metadata contains no secrets | ❌ | ✅ `AuditService` contract + review | `AuditLogPolicyTest`, `LoginRateLimitTest` |
| 27 | Role transition never loses the original role | ❌ | ✅ `RoleTransitionService` (`base_role_id` + history, idempotent) | `TemporaryLeadershipTest` |
| 28 | Primary TL is view-only while covered | ❌ | ✅ `User::isViewOnlyLeader()` via `Department::effectiveLeader()` | `TemporaryLeadershipTest` |
| 29 | Login brute force is limited | ❌ | ✅ `RateLimiter` (username+IP) | `LoginRateLimitTest` |
| 30 | Temporary password must be rotated | ❌ | ✅ `EnsurePasswordChanged` middleware | `ForcedPasswordChangeTest` |

**Deliberate gaps.** Invariants 14, 15 and 17 cannot be expressed as standard SQL CHECK
constraints because they span tables or depend on time-varying state. Each is enforced in a
service, validated in a Form Request, and covered by a test. A PostgreSQL trigger could add
defence-in-depth for #14 — recorded as an open technical item, not a business question.
