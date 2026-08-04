# Agency OS — Remaining Open Items

**Q1–Q30 are CLOSED.** All thirty business questions were answered by the product owner
and are recorded in `APPROVED-DECISIONS-Q1-Q30.md`; deviations from the BRD are logged in
`CHANGE-REQUEST-REGISTER.md`.

What remains below are **technical configuration values and deployment choices** — not
business workflow questions. None of them blocks Phase 1B.

---

## A. Storage & retention parameters (Q29 — values only)

The retention *model* is approved. These numbers are deployment/SRS configuration and must
be set before any cleanup capability is switched on. **Automatic destructive deletion stays
disabled until every value is approved.**

| Parameter | Needed value | Suggested starting point |
|---|---|---|
| Warning storage threshold | % of capacity that raises an Admin warning | 70 % |
| Critical storage threshold | % that triggers escalation | 85 % |
| Runway warning window | days of projected remaining capacity that triggers a warning | 30 days |
| Cleanup safety margin | % of free space to preserve after a cleanup | 20 % |
| Eligible entities | which entities may ever be cleaned (proposal: `notifications`, `notification_deliveries`, `chat_messages`, `chat_digest_batches`) | — |
| Minimum retention per entity | days each entity is kept regardless of pressure | notifications 180 · chat 365 |
| Export before deletion | whether archived data is exported first, and to where | yes — object storage |
| Never purged | confirm `audit_logs` are exempt permanently | yes |

## B. Security & session configuration

| Item | Current value | Needs confirmation |
|---|---|---|
| Password minimum length | 8, confirmed | complexity rules? rotation period? |
| Login throttle | 5 attempts / 60 s per username+IP | acceptable, or stricter? |
| Session lifetime | 120 min (`config/session.php`) | idle timeout for the "session expired" screen in the demo |
| Queue worker | `database` driver configured | production driver + supervision (Q28 model approved, driver is deployment) |
| Session cookie security | `SESSION_SECURE_COOKIE=false` locally | must be `true` in production (HTTPS) |
| Disabled-account message | login states the account is disabled | confirm this info-disclosure trade-off (matches the approved UI) |

## C. Email infrastructure (Q27/Q28 — model approved, values pending)

| Item | Needs |
|---|---|
| SMTP host, port, encryption, credentials | the company's official mail account details (environment only, never the database) |
| From address / display name | e.g. `no-reply@company.tld`, "Agency OS" |
| Bounce & delivery feedback | is a webhook available, or is status limited to SMTP response? |
| Queue driver in production | database vs Redis, and the worker supervision method |
| Retry policy | max attempts and backoff schedule (proposal: 5 attempts, exponential to 1 h) |

## D. Deployment

| Item | Needs |
|---|---|
| PHP / Laravel target | shipped as PHP ^8.2 / Laravel 12 |
| PostgreSQL version | 14+ works; 16 recommended |
| `btree_gist` extension | the database role needs `CREATE EXTENSION` rights, or the extension must be pre-created |
| Backup schedule & retention | required before go-live |
| Scheduler | `php artisan schedule:run` every minute (cron) — required for Q13 temporary-TL transitions |
| Timezone | `Africa/Cairo` fixed in `config/app.php` |

## E. Modelling questions raised by implementation (for Phase 1B design review)

1. **Project hold accounting (Q23).** Does a project hold write child `task_holds` rows for
   each paused task, or is the pause resolved dynamically at read time? *Recommendation:
   child rows, so the deadline engine keeps a single accounting path.* See ERD notes §8.
2. **Final-output grouping (Q25).** Confirm adding `submission_no` and
   `superseded_by_output_id` to `task_step_outputs`, since "all links in the approved
   submission are final" cannot be derived from "latest row". See ERD notes §9.
3. **Same-department temporary TL (Q3).** Enforced in `TemporaryLeadershipService` and the
   Form Request, and covered by tests (see `CONSTRAINT-PROTECTION-MATRIX.md` #14). Add a
   PostgreSQL trigger for defence-in-depth, or is service-level validation sufficient?
4. **`on_leave` status.** Now that Q14 defines the primary TL's leave behavior, confirm
   whether `users.status = 'on_leave'` is the mechanism that *starts* leave (and therefore
   gates Q15), or whether leave is recorded solely by the temporary-TL assignment period.

> Item E4 is the only one with any Phase 1B sequencing risk; the rest can be settled during
> implementation without rework.
