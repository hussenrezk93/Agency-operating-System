# Agency OS — Business Requirements Document (BRD)

**Version:** 1.1 — Final, unified after merging Change Request CR-001 (email notifications + project WhatsApp group link)
**Status:** Final copy, ready for review and approval
**Prepared by:** Implementation Team
**Submitted to:** Agency OS Management
**Issue date:** 25/07/2026
**Confidentiality:** Confidential — project use and authorized reviewers only
**Timezone:** Africa/Cairo
**Language:** Arabic + English UI; department, project, and task names are stored in English.

> Document authority: any behavior or function not written in this document is out of the current scope, or requires a formal Change Request before implementation.

## Version history

| Version | Date | Status | Description |
|---|---|---|---|
| 0.1 | 24/07/2026 | Previous draft | Initial idea and inter-department workflow |
| 0.5 | 24/07/2026 | Previous draft | Locked permissions, Temporary TL, projects, clients, reports |
| 1.1 | 25/07/2026 | **Current — for review** | Full CR-001 merge: scope, projects, accounts, notifications, audit, NFRs, acceptance criteria, appendices |

---

## B. Executive Summary

Agency OS needs a unified internal system that organizes task creation and distribution between departments, review, and approval — while always making clear who currently owns each stage, tracking deadlines, storing outputs/links, and measuring monthly employee/department performance.

The system runs a flexible, sequential path: a task first reaches the receiving department's Team Leader (TL). The TL assigns one employee (or themself), sets a start/end date, receives and reviews the employee's output, and after approval picks the next allowed department or finishes the task.

**Core business outcome:** one platform that is the single source of truth for task status, ownership, outputs, approvals, delays, and reports — with a complete audit trail visible only to Admin.

Version 1.1 adds a helper email channel for all system notifications (after personal-email verification), and lets a project store an optional WhatsApp group invite link, distributed to derived project members — with no direct WhatsApp Business API integration or automatic group membership management.

---

## 2. Project Overview

Agency OS is an internal, single-company web application, bilingual (Arabic/English), used to organize work between departments such as Marketing, Content, Photography/Videography, Moderator, Design, and Editor. A Manager starts a task from any department; a TL starts it from their own department or another department the Admin-configured rules allow.

More than one department never works the same stage in parallel within one task. When truly parallel work is needed, more than one task is created inside the same project.

## 3. Project Goals & Success Criteria

- Clearly identify who currently owns a task and the next required action.
- Guarantee every stage passes through the department TL before assignment to an employee and before moving to the next department.
- Give the Manager full visibility over tasks, projects, outputs, and performance.
- Prevent users from viewing or performing any action outside their permissions.
- Monthly performance scoring based on deadline compliance.
- Keep a complete history of changes and sensitive actions in the Audit Log.
- Support Temporary TL assignment without disrupting work or losing tasks.

## 4. Project Scope

### 4.1 In scope

- User/role/department management, primary and Temporary TL.
- Standalone tasks or tasks that belong to a project.
- Client and project management, participating departments, project reference links.
- Assigning a task to one employee or to the TL themself; review, approval, and transfer to the next department.
- On Hold, Redirect, Cancelled, Completed per permissions.
- Reference and Output links, no in-system file uploads.
- Stage-scoped private comments, internal chat, in-app + email notifications.
- Monthly reports and deadline-compliance indicators.
- Admin-managed inter-department routing rules and output visibility rules.
- Optional project-level WhatsApp group invite link, distributed via in-app + email notifications.

### 4.2 Out of current scope

- External client portal.
- Self-registration for users.
- File upload/storage inside the system.
- Reopening a task or project after completion.
- Direct WhatsApp Business API integration, in-system group creation, automatic member add/remove, or reading WhatsApp messages.
- Payroll or attendance system.
- Email-based password recovery — password reset stays Manager/Admin-only per approved permissions.

## 5. Users & Roles

| Role | Core responsibilities | Constraints |
|---|---|---|
| **Admin** | Manage global settings, inter-department routing rules, output-visibility permissions, Manager accounts + their personal email, view Audit Log | Does not participate in daily operations, cannot read chat content or task details |
| **Manager** | Full operational management: users, departments, Temporary TL, Employee/TL account + personal email creation, clients, projects, all tasks/outputs/reports | Not required to be a regular task executor |
| **Team Leader** | Create tasks/projects/clients, receive department tasks, assign, set deadlines, review, request changes, choose next department or finish task | Belongs to exactly one department; cannot approve their own self-assigned work |
| **Employee** | Execute assigned task, view previous references/outputs, add outputs, comment, submit | Cannot create tasks, cannot send to another department, cannot approve a stage |

## 6. Organizational Structure & Departments

- Each employee belongs to exactly one department.
- Each TL leads exactly one department.
- A new department cannot be created without assigning a primary TL in the same operation.
- Exactly one active primary TL per department; at most one Temporary TL during a defined period.
- Disabling an employee with open tasks returns their active stages to the department TL as `Waiting Assignment`.
- Disabling a department with active tasks sends those cases to the Manager to pick an alternate department.
- Employees and departments are disabled, never hard-deleted, to preserve history.

## 7. Clients & Projects

### 7.1 Client data

| Field | Rule |
|---|---|
| Client name | Required |
| Short description | Optional |
| Phone number | Required |
| Company email | Optional |
| Website | Optional |
| Status | Active or Inactive |

### 7.2 Project rules

- Every project belongs to exactly one client; a client can have many projects.
- Manager or TL may create a project; a new client can be created inline during project creation.
- A TL adds their own department plus departments they are allowed to send tasks to.
- A project has a name, description, client, participating departments, and general reference links.
- Project start date = creation moment; actual close date is recorded.
- Project statuses: **Active**, **Completed**, **Cancelled** only.
- Completion is manual, done by the Manager or the project's creator.
- A completed project is read-only, accepts no new tasks, and is never reopened.
- Cancelling a project automatically cancels every incomplete task inside it.

### 7.3 Project WhatsApp group link

- The project creator creates the WhatsApp group manually outside the system, then saves the invite link inside the project. The field is optional; a project may be created without it.
- Permission to add/edit the link: Manager, and the project creator if they are a Team Leader, as long as the project is Active.
- Every link edit creates a new version number and resends the invite to all current project members once for that version.
- Project membership is derived from participating departments: every active user in those departments, the primary TL, the Temporary TL during their assignment, the project creator, and the Manager.
- Adding a new department sends the invite only to that department's members; a new employee joining a participating department is auto-invited automatically as long as the project is Active.
- When an employee moves to another department, they receive invites for their new department's projects, and an alert reaches the previous department's TL to manually remove them from prior groups.
- Disabling a user or removing a department from a project immediately stops invites and alerts the TL to complete manual removal from the group.
- After a project becomes Completed or Cancelled, no new invites are sent and the link becomes read-only.
- The system never integrates with the WhatsApp Business API, never creates the group, never adds/removes members, and never reads messages.

| Event | Required behavior |
|---|---|
| Project created with a link | Send invite to all participating-department members |
| New department added | Send invite to the new department's members only, no repeats for others |
| Link added after project creation | Send invite to all current members at the time of addition |
| Link changed | Create a new version and resend to all current members |
| New employee joins a participating department | Auto-send invite as long as project is Active |
| User disabled or department removed | Stop sending; alert TL for manual removal from the group |
| Completed or Cancelled | No new invites; link becomes read-only |

## 8. Task Creation & Data

| Field | Description |
|---|---|
| Task number | Automatic, unique, used in search/reports |
| Title | Required, written in English |
| Brief/description | Explains the requirement and expected result |
| Priority | Low / Medium / High / Urgent; Urgent sorts to the top of lists |
| First department | Required, drawn from allowed departments |
| Reference Links | Optional |
| Notes | Optional |
| Project | Optional; omitting it allows a standalone task |
| Route | Recorded automatically: departments, dates, entry/exit time |

- Only Manager and TL can create tasks.
- Manager starts a task from any department; TL starts it from their own department or an allowed one.
- The creator can edit task data before an employee is assigned; after work starts, the department is changed only via Redirect.
- Priority cannot change after execution starts.
- A task can be saved as a draft before sending, and deleted only before it enters the workflow.

## 9. Task Cycle & Review

1. Manager or TL creates the task and sets the first department and base data.
2. The task first reaches the department TL in `Waiting Assignment`.
3. The TL assigns one employee or assigns the task to themself, and sets start/end dates.
4. The employee sees the task and starts execution; the system logs the first-open time and shows it to the TL only.
5. The employee adds at least one Output link, then submits the work for review.
6. The TL reviews the submission and chooses Approve or Request Changes.
7. On Request Changes, the task returns to the same employee with a mandatory comment.
8. After Approve, the current TL chooses **Send to Next Department** or **Finish Task**.
9. When choosing the next department, only allowed departments are shown.
10. On Finish Task, the task becomes Completed, read-only, and never reopened.

> **Self-assigned TL case:** if a TL assigns the stage to themself, the Manager is the sole reviewer — a TL can never approve their own work.

## 10. Task Statuses & Special Actions

| Status/Action | Business rule |
|---|---|
| Waiting Assignment | Reached the department, awaiting employee assignment |
| In Progress | Employee or TL working the stage |
| Under Review | Submitted, awaiting review |
| Changes Requested | Returned to the same employee with a comment |
| Approved | Stage approved; can be transferred or finished |
| On Hold | Manager or task creator pauses it temporarily with a mandatory reason |
| Overdue | Deadline passed without stage completion |
| Redirected | Manager-only action to correct the route, with a mandatory reason |
| Cancelled | Manager or task creator cancels with a mandatory reason; never reopened |
| Completed | Closed read-only, no edits/links/comments |

## 11. Deadlines & Reminders

- Each department sets its own stage deadline through the receiving TL.
- TL sets start and end date; end time is automatically 11:59 PM Cairo time.
- Only the department TL can edit the stage deadline after assignment, no reason required.
- Changing the employee requires the TL to set a new deadline for the new employee.
- **On Hold pauses the duration clock**; after resuming, the end date is extended by the pause duration.
- A reminder fires 24 hours before the deadline, and a notice fires when the deadline is exceeded — to the employee and TL only.

| Event | In-app | Email |
|---|---|---|
| 24h before deadline | Immediate | Immediate |
| Deadline exceeded | Immediate | Immediate |
| Deadline edited | Immediate | Immediate |
| On Hold / Resume | Immediate | Immediate |
| Cancel / Redirect | Immediate | Immediate |

### 11.1 Notification channels & sending rules

- Every logical in-app notification is also emailed to the verified personal email; the in-app notification remains the official record.
- The in-app notification is mandatory and cannot be disabled by the user; email is a helper channel only.
- The email includes the task title, project name, and department where applicable.
- A user does not receive email notifications until their email is verified, with a clear in-app warning shown.
- Email sending is best-effort; delivery failure never blocks any state transition or operation, and is logged in an independent delivery log with retry.
- Chat notifications are immediate in-app; email batches new messages into one digest every 2 hours, and no digest is sent if there are no new messages.

## 12. Temporary Team Leader

- Only the Manager appoints a Temporary TL for a specific department, with a start and end date.
- No more than one Temporary TL per department during the same period.
- During the period the primary TL becomes **View Only** on their department and cannot perform leadership actions.
- The Temporary TL receives the department's permissions, old and new submissions, and can approve them.
- The primary TL's active personal tasks move to the Temporary TL.
- The Manager can end the assignment early; permissions automatically return to the primary TL at the end.
- A clear notice is shown to users about the presence of a Temporary TL and the assignment period.

## 13. Links, Outputs & Comments

- The system stores links only (e.g., Google Drive) and does not upload files internally in v1.
- More than one Reference and more than one Output can be added to a task or project.
- A stage cannot be submitted without at least one Output.
- An output link is never edited after being added; on error, a new link is added instead.
- The current employee sees outputs of previous stages of the same task as reference.
- Stage comments are private to the employee and department TL and are not visible to the next department.

## 14. Internal Communication

| Conversation type | Participants |
|---|---|
| Employee–TL | The employee and their department's TL |
| Department Group | The TL and department employees |
| Direct TL | Team Leaders only |
| All TLs | All Team Leaders |
| Manager + TLs | Managers and all Team Leaders |

- Chat supports text and links only.
- Messages cannot be edited after sending.
- Only the message owner can delete it, and it disappears for everyone permanently.
- The Audit Log records the delete event, username, time, and conversation, without keeping the message text.
- There is no separate in-task chat; stage comments are used for execution-related communication.
- Chat notifications stay immediate in-app, while emailed in an Email Digest every 2 hours only.

## 15. Permissions & Visibility

| Action | Admin | Manager | TL | Employee |
|---|---|---|---|---|
| Manage routing rules & output visibility | Full | No | No | No |
| Manage users & departments | No | Managers only | Employees, TLs, departments | No |
| Create task/project | No | Yes | Yes, per permission | No |
| Assign employee & set deadline | No | On operational need | Within own department | No |
| View all tasks & outputs | No | Yes | Own department + Admin rules | Assigned + prior references |
| Approve / Request Changes | No | When reviewing TL self-assignment | Within current stage | No |
| Redirect | No | Yes, with reason | No | No |
| Audit Log | Yes, only | No | No | No |

> **Output-viewing rule:** Admin sets the general rules for which department's TL can view another department's outputs. Employees don't get this general permission but see outputs of prior stages of the same task as reference. Admin's authority is to configure the rules only — it grants no right to view output content or task detail.

## 16. Dashboards & Search

### 16.1 Employee dashboard
- Current, overdue, changes-requested, and completed tasks.
- Current month's score.
- Notifications and chat.

### 16.2 TL dashboard
- Tasks awaiting assignment.
- Department tasks by employee.
- Submissions awaiting review.
- Overdue items and department performance.
- "My Tasks" section for tasks the TL assigned to themself.
- First-view time of the employee for the task.

### 16.3 Manager dashboard
- Task summary by status and department.
- Active projects.
- Overdue tasks.
- Department, employee, and TL performance.
- Cases needing Reassignment or Redirect.
- Currently active Temporary TLs.

Search supports task number, task title, or project name, with Urgent sorted to the top.

## 17. Reports & Performance Indicators

Reports are prepared monthly; a stage belongs to the month its deadline falls in. If a user has no due stages that month, the score shows **N/A** instead of zero.

**Employee performance formula:**

```
Performance Score = (number of stages completed on time ÷ total stages due that month) × 100
```

- If the deadline passes without submission, the stage immediately becomes overdue and scores zero.
- The number of change-request rounds does not lower the score if the final version is approved before the deadline.
- Periods where the task is On Hold do not count as delay time.
- TL report shows their personal-task performance and the team's overdue rate separately.
- Department report shows due stage count, on-time count, overdue count, and compliance rate.
- Manager sees a comparison across departments, employees, and TLs.

## 18. Account Management & Security

- Login via Username and Password.
- No Sign Up or self-registration.
- Manager creates Employee/TL accounts; Admin creates Manager accounts.
- The system generates a temporary password and forces the user to change it at first login.
- TL never resets passwords.
- Manager resets any Employee/TL password; Admin resets Manager passwords.
- Accounts are disabled instead of deleted, to preserve historical reference.
- Permissions are enforced in the backend, not just by hiding buttons.

### 18.1 Personal email & verification

- Personal email is required and unique per user; an account is never saved without one.
- Manager enters the email when creating an Employee/TL account; Admin enters it when creating a Manager account.
- A user can edit their own email from their profile; a Manager can edit it for them; every edit is logged in the Audit Log with old and new value.
- Every new or changed email goes through verification via a link valid for 24 hours, and no email notifications are received until verification completes.
- Email is never used for password recovery; password reset stays with Manager and Admin per current permissions.

## 19. Audit Log

The audit log is **append-only** and viewable only by Admin. It grants Admin no operational authority inside tasks and no right to read chat content.

Logged events include: user/department creation and disabling, role/leader changes; task/project/client creation and base-data edits; assignment, reassignment, first view, deadline edits, submission, review, transfer, and finish; On Hold/Resume/Redirect/Cancelled; Temporary TL assignment and ending; password resets and key security events; chat message deletion (event only, no message content); WhatsApp group link add/edit/delete with version, user, and time; invite send/resend with recipient user and qualifying department; project department add/remove as an event affecting project membership; personal email add/change with old and new value; email verification success/failure or link expiry; repeated email delivery failure or a hard bounce.

## 20. Non-Functional Requirements

| Domain | Requirement |
|---|---|
| Usability | Clear Arabic/English UI, responsive on desktop and mobile, always shows current owner and next action |
| Security | Password hashing, secure sessions, server-side permission checks, protected Audit Log |
| Performance | Fast dashboard/search loading for a small-company environment, with room to scale |
| Reliability | State transitions, assignment, and project cancellation run transactionally, no partial data |
| Maintainability | Modular architecture, normalized database ready for client history and future integrations |
| Traceability | Every sensitive action is tied to a user, date, time, and clear entity |
| Language & timezone | Arabic/English UI, Africa/Cairo time |
| Mail sending | Send queue with retry and a per-channel delivery log; failure/bounce handling never blocks the system |
| Domain setup | Documented sender domain using SPF, DKIM, DMARC; rate throttling to reduce spam-folder delivery |
| Mail privacy | Personal email classified as PII; minimum-access handling and tracking |
| External dependency | Documented reliance on the external mail provider; monitor repeated failure/bounce |

### 20.1 Email & group-link risks

| Risk | Impact | Mitigation |
|---|---|---|
| Group invite link leak | Unauthorized people join the project group | Limit link availability to project members; log every send; allow link replacement |
| Widening the circle of those who know | Adding a whole department may hand the link to people not directly on the project | Review participating departments before adding, restrict to relevant departments |
| No automatic removal | A disabled/moved employee stays in the group | Mandatory TL alert on disable/move, with a documented manual step |
| Client data over personal email | Some work data leaves outside company control | Approved management decision, reduce sensitive content, recommend a company email later |
| Mail fatigue | Important notices get ignored or marked as spam | Batch chat notices every 2 hours; keep task notices immediate |
| Mail failure or bounce | The helper channel doesn't reach the user | Delivery log + retry + alert Manager on repeated failure |

## 21. Out of Scope

- Managing Google Drive files or their permissions from inside the system.
- WhatsApp Business API integration, in-system group creation, member management, or reading WhatsApp messages.
- Manual quality scoring or task difficulty points.
- Reopening completed tasks or projects.
- Two departments working the same task stage in parallel.
- A full external client portal; the database is only prepared for future Client History.
- Using email for password recovery, or treating it as the official channel instead of in-system notifications.

## 22. Business Acceptance Criteria

1. A task never reaches an employee before passing through the department TL.
2. No submission is allowed without at least one Output.
3. A TL can never approve a stage they executed themself; the Manager reviews it.
4. Only Admin-allowed departments ever appear as the next department.
5. **On Hold pauses the deadline duration** and extends the end date by the pause duration.
6. A task and project are read-only after Completed and are never reopened.
7. Monthly scoring reflects deadline compliance for stages due in that month.
8. TL sees the employee's first-open time; the employee never sees the TL's read time.
9. Only Admin sees the Audit Log.
10. Cancelling a project cancels every incomplete task inside it.
11. Historical records persist after a user or department is disabled.
12. A WhatsApp group invite is never sent unless the project holds a saved link and is Active.
13. Every active user in a participating department receives the invite once per link version, no repeats.
14. Adding a new department to a project sends the invite to the new department's members only.
15. Changing the group link creates a new version and resends to all current members.
16. A user account is never saved without a unique personal email.
17. A user does not receive email notifications before their email verification completes.
18. Every in-app notification has a matching email, except chat messages which are batched every 2 hours.
19. Email delivery failure never blocks the in-app notification or any state transition.
20. Disabling a user immediately stops invites/emails and alerts the TL to manually remove them from project groups.
21. A completed or cancelled project sends no new invites and its group link becomes read-only.

## 23. Deliverables & Project Stages

### 23.1 Expected deliverables
- Approved BRD document.
- Editable ERD diagram.
- Detailed SRS document.
- Wireframes and Arabic/English UX/UI design.
- Backend, Frontend, and database.
- Test plan, UAT, user guide, and deployment plan.

### 23.2 Proposed stages
1. Approve BRD and ERD.
2. Prepare SRS with detailed states/permissions rules.
3. Design and approve UX/UI.
4. Develop database and Backend.
5. Develop UI, dashboard, and chat.
6. Security and functional testing.
7. UAT and address feedback.
8. Deployment, training, and support.

## 24. Approvals

By signing this document, the parties acknowledge the items herein represent the approved business understanding of Agency OS, and that any additional function or material change after approval is subject to a formal Change Request stating its impact on scope, duration, and cost.

*(Signature table: Agency OS Management / Project Owner / Implementation Team — dates and signatures pending)*

---

## Appendix A — Task Route

| Stage | Owner | Action | Resulting status |
|---|---|---|---|
| 1 | Task creator | Create and send the task to the first department | Waiting Assignment |
| 2 | Department TL | Assign an employee or self-assign; set the deadline | In Progress |
| 3 | Employee / TL | Execute, add Output, submit | Under Review |
| 4A | TL / Manager (if self-assigned) | Request Changes with a comment | Changes Requested |
| 4B | TL / Manager (if self-assigned) | Approve | Approved |
| 5A | Current TL | Send to an allowed next department | Waiting Assignment (new department) |
| 5B | Current TL | Finish Task | Completed |
| X | Manager / task creator | Cancel with a reason | Cancelled |

## Appendix B — Notification Matrix

| Event | Recipient | In-app | Email |
|---|---|---|---|
| Task arrives at department | Effective TL | Immediate | Immediate |
| Employee assigned/reassigned | The assigned employee | Immediate | Immediate |
| Employee first opens task | Department TL | Immediate | Immediate |
| Stage submitted for review | TL or Manager (for review) | Immediate | Immediate |
| Changes Request | Employee | Immediate | Immediate |
| Stage approved or transferred | Affected parties | Immediate | Immediate |
| 24h before deadline | Employee + TL | Immediate | Immediate |
| Deadline exceeded | Employee + TL | Immediate | Immediate |
| On Hold / Resume / Redirect / Cancel | Affected parties | Immediate | Immediate |
| Temporary TL assigned or ended | Department and stakeholders | Immediate | Immediate |
| Project WhatsApp invite | Project members | Immediate | Immediate |
| New chat message | Conversation members | Immediate | Batched digest every 2 hours |
| Email verification | Account owner | N/A | Immediate |

## Appendix C — Key Data Entities

| Group | Key entities |
|---|---|
| Identity & Permissions | Users, Roles, Departments, Leadership Assignments, Routes, Output Access |
| Clients & Projects | Clients, Projects, Project Departments, Project Links, WhatsApp Group Link and Versions, Project Invite Deliveries |
| Tasks & Execution | Tasks, Task Steps, Assignments, Outputs, Reviews, Comments, Holds, Redirects, Status History |
| Communication | Conversations, Members, Messages, Notifications, Notification Deliveries, Chat Digest Batches |
| Reports & Audit | Monthly Performance Snapshots, Audit Logs |
| Email Verification | Personal Email, Email Verification Tokens, Email Verified At |

### C.1 — CR-001 impact on the data model

The `notifications` table represents the logical in-system event, while `notification_deliveries` holds each channel's status separately, allowing future channels to be added without a fundamental change to the notification model.

| Table | Field | Type & rule |
|---|---|---|
| Projects | whatsapp_group_url | text, null |
| Projects | whatsapp_group_label | varchar, null |
| Projects | whatsapp_link_version | int, default 0 |
| Projects | whatsapp_link_updated_at | timestamp, null |
| Projects | whatsapp_link_updated_by | FK Users.id, null |
| Users | personal_email | varchar, not null, unique |
| Users | email_verified_at | timestamp, null |

| New table | Purpose & key fields |
|---|---|
| Project Invite Deliveries | Project, User, Department, Link Version, Channel, Status, Sent At, Error — unique on Project + User + Link Version |
| Notification Deliveries | Notification, Channel, Status, Provider Message ID, Error, Sent At |
| Email Verification Tokens | User, Token Hash, Expires At, Consumed At, Created At |
| Chat Digest Batches | User, Window Start, Window End, Message Count, Sent At, Status |
