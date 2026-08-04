# Agency OS — Reference Documentation

Source-of-truth planning documents for the project, kept here so implementation work can be checked against them directly.

| File | Contents |
|---|---|
| [01-BRD-v1.1.md](01-BRD-v1.1.md) | Business Requirements Document v1.1 — scope, roles, task/project lifecycle, permissions, notifications, security, acceptance criteria |
| [02-ERD-v1.2-Corrected.md](02-ERD-v1.2-Corrected.md) | Entity-Relationship Diagram v1.2 — every table, field, type, and constraint |
| [03-Phase0-Technical-Plan.md](03-Phase0-Technical-Plan.md) | Phase 0 technical plan — modules, permissions matrix, state-transition tables, scheduled jobs, events, security checklist, and the resolved BRD/ERD conflicts |
| [04-Development-Roadmap-Phases-AR.md](04-Development-Roadmap-Phases-AR.md) | Phase 0 → Phase 10 delivery roadmap (Arabic), with per-phase deliverables, exit criteria, and the phase-approval checklist |

## How these relate to the rest of the repo

- Business-question answers (Q1–Q30) live in `../APPROVED-DECISIONS-Q1-Q30.md`.
- Deviations from these documents are logged in `../CHANGE-REQUEST-REGISTER.md`.
- Remaining deployment/config-only open items are in `../OPEN-DECISIONS.md`.
- Delivery status per phase/slice is in `../IMPLEMENTATION-STATUS.md`.

These four documents are the frozen baseline (BRD v1.1 + ERD v1.2). Any new feature or behavior not covered here needs a Change Request before implementation, per the BRD's own document-authority clause.
