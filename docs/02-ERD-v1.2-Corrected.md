# Agency OS — Corrected ERD v1.2

Aligned with BRD v1.1 — workflow, projects, communication, email, and audit.

## Entity groups

**Identity & Organization:** roles · departments · users · leadership assignments · routing/access
**Clients & Projects:** clients · projects · project departments · WhatsApp link versions · invite deliveries
**Tasks & Workflow:** tasks · steps · assignments · outputs/reviews · holds/redirects/history
**Notifications & Email:** notifications · channel deliveries · email verification
**Chat & Digests:** conversations · members · messages · 2-hour email digests
**Performance & Audit:** monthly snapshots · append-only audit log

## Key business rules

- Deadline status is separate from workflow status.
- Completed tasks and projects are read-only and cannot be reopened.
- One active primary TL per department; temporary TL periods cannot overlap.
- Task submission requires at least one output link.
- Self-assigned TL work is reviewed by a Manager.
- On Hold pauses the deadline and extends it by the hold duration.
- WhatsApp stores/distributes an invite link only; no WhatsApp API.
- Email requires a verified personal email; chat email is digested every 2 hours.

---

## Identity & Organization

### roles
| Field | Type |
|---|---|
| PK id | bigint |
| code | varchar, unique |
| name | varchar |

### departments
| Field | Type |
|---|---|
| PK id | bigint |
| name | varchar, unique |
| is_active | boolean |
| created_at | timestamptz |

### users
| Field | Type |
|---|---|
| PK id | bigint |
| FK role_id | roles.id |
| FK department_id | departments.id, null |
| full_name | varchar |
| username | varchar, unique |
| password_hash | text |
| status | active/inactive/on_leave |
| must_change_password | boolean |
| personal_email | varchar, not null, unique |
| email_verified_at | timestamptz, null |
| created_at | timestamptz |
| updated_at | timestamptz |

### department_leadership_assignments
| Field | Type |
|---|---|
| PK id | bigint |
| FK department_id | departments.id |
| FK user_id | users.id |
| assignment_type | primary/temporary |
| start_date | date |
| end_date | date, null |
| ended_early_at | timestamptz, null |
| is_active | boolean |
| FK assigned_by | users.id |
| created_at | timestamptz |
| **UQ** | 1 active primary per department |
| **EXCLUDE** | no overlapping temporary periods |
| **UQ** | one active led department per user |

### department_routes
| Field | Type |
|---|---|
| PK id | bigint |
| FK from_department_id | departments.id |
| FK to_department_id | departments.id |
| is_allowed | boolean |
| FK updated_by | users.id |
| updated_at | timestamptz |
| **UQ** | from + to |

### department_output_access
| Field | Type |
|---|---|
| PK id | bigint |
| FK viewer_department_id | departments.id |
| FK source_department_id | departments.id |
| scope | all_outputs/final_only |
| is_allowed | boolean |
| FK updated_by | users.id |
| updated_at | timestamptz |
| **UQ** | viewer + source |

---

## Clients, Projects & WhatsApp

### clients
| Field | Type |
|---|---|
| PK id | bigint |
| name | varchar |
| short_description | text, null |
| phone | varchar, not null |
| company_email | varchar, null |
| website_url | text, null |
| status | active/inactive |
| FK created_by | users.id |
| created_at | timestamptz |
| updated_at | timestamptz |

### projects
| Field | Type |
|---|---|
| PK id | bigint |
| FK client_id | clients.id |
| project_code | varchar, unique |
| name | varchar |
| description | text |
| status | active/completed/cancelled |
| FK created_by | users.id |
| started_at | timestamptz |
| completed_at | timestamptz, null |
| FK completed_by | users.id, null |
| cancelled_at | timestamptz, null |
| FK cancelled_by | users.id, null |
| cancelled_reason | text, null |
| whatsapp_group_url | text, null |
| whatsapp_group_label | varchar, null |
| whatsapp_link_version | int, default 0 |
| whatsapp_link_updated_at | timestamptz, null |
| FK whatsapp_link_updated_by | users.id, null |

### project_departments
| Field | Type |
|---|---|
| PK id | bigint |
| FK project_id | projects.id |
| FK department_id | departments.id |
| is_active | boolean |
| added_at | timestamptz |
| removed_at | timestamptz, null |
| **UQ** | project + department |

### project_links
| Field | Type |
|---|---|
| PK id | bigint |
| FK project_id | projects.id |
| FK added_by | users.id |
| url | text |
| label | varchar, null |
| created_at | timestamptz |

### project_whatsapp_link_versions
| Field | Type |
|---|---|
| PK id | bigint |
| FK project_id | projects.id |
| version_no | int |
| group_url | text, null |
| group_label | varchar, null |
| action_type | created/updated/removed |
| FK created_by | users.id |
| created_at | timestamptz |
| is_current | boolean |
| **UQ** | project + version_no |
| **UQ** | one current version per project |

### project_invite_deliveries
| Field | Type |
|---|---|
| PK id | bigint |
| FK project_id | projects.id |
| FK user_id | users.id |
| FK department_id | departments.id, null |
| FK link_version_id | project_whatsapp_link_versions.id |
| FK notification_id | notifications.id, null |
| recipient_source | department/creator/manager/temp_tl |
| channel | in_app/email |
| status | queued/sent/failed |
| sent_at | timestamptz, null |
| error | text, null |
| **UQ** | project + user + version + channel |

---

## Tasks — Core, Status & Routing

### tasks
| Field | Type |
|---|---|
| PK id | bigint |
| task_code | varchar, unique |
| FK project_id | projects.id, null |
| title | varchar |
| brief | text |
| notes | text, null |
| priority | low/medium/high/urgent |
| lifecycle_status | draft/active/on_hold/completed/cancelled |
| FK created_by | users.id |
| FK current_step_id | task_steps.id, null |
| created_at | timestamptz |
| completed_at | timestamptz, null |
| FK completed_by | users.id, null |
| cancelled_at | timestamptz, null |
| FK cancelled_by | users.id, null |
| cancelled_reason | text, null |

### task_reference_links
| Field | Type |
|---|---|
| PK id | bigint |
| FK task_id | tasks.id |
| FK added_by | users.id |
| url | text |
| label | varchar, null |
| created_at | timestamptz |

### task_steps
| Field | Type |
|---|---|
| PK id | bigint |
| FK task_id | tasks.id |
| FK department_id | departments.id |
| sequence_no | int |
| workflow_status | waiting_assignment/in_progress/under_review/changes_requested/approved/redirected/cancelled |
| deadline_status | not_started/on_time/due_soon/overdue/closed |
| created_at | timestamptz |
| submitted_at | timestamptz, null |
| approved_at | timestamptz, null |
| completed_at | timestamptz, null |
| **UQ** | task + sequence_no |

### task_holds
| Field | Type |
|---|---|
| PK id | bigint |
| FK task_id | tasks.id |
| FK task_step_id | task_steps.id |
| FK created_by | users.id |
| reason | text |
| started_at | timestamptz |
| ended_at | timestamptz, null |
| FK resumed_by | users.id, null |
| extension_interval | interval, null |

### task_redirects
| Field | Type |
|---|---|
| PK id | bigint |
| FK task_id | tasks.id |
| FK from_step_id | task_steps.id |
| FK to_department_id | departments.id |
| FK redirected_by | users.id |
| reason | text |
| created_at | timestamptz |

### task_status_history
| Field | Type |
|---|---|
| PK id | bigint |
| FK task_id | tasks.id |
| FK task_step_id | task_steps.id, null |
| event_type | varchar |
| from_status | varchar, null |
| to_status | varchar, null |
| FK changed_by | users.id |
| reason | text, null |
| created_at | timestamptz |

---

## Task Execution, Outputs & Review

### task_step_assignments
| Field | Type |
|---|---|
| PK id | bigint |
| FK task_step_id | task_steps.id |
| FK assignee_id | users.id |
| FK assigned_by | users.id |
| start_date | date |
| due_date | date |
| first_seen_at | timestamptz, null |
| assigned_at | timestamptz |
| ended_at | timestamptz, null |
| end_reason | text, null |
| **UQ** | one active assignment per step |

### task_step_outputs
| Field | Type |
|---|---|
| PK id | bigint |
| FK task_step_id | task_steps.id |
| FK added_by | users.id |
| url | text |
| label | varchar, null |
| is_final | boolean |
| created_at | timestamptz |
| **RULE** | immutable after insert |

### task_step_comments
| Field | Type |
|---|---|
| PK id | bigint |
| FK task_step_id | task_steps.id |
| FK author_id | users.id |
| body | text |
| created_at | timestamptz |

### task_step_reviews
| Field | Type |
|---|---|
| PK id | bigint |
| FK task_step_id | task_steps.id |
| FK reviewer_id | users.id |
| decision | approved/changes_requested |
| comment | text, null* |
| reviewed_at | timestamptz |
| **CHECK** | comment required when decision = changes_requested |

---

## Notifications & Email

### notifications
| Field | Type |
|---|---|
| PK id | bigint |
| FK user_id | users.id |
| type | varchar |
| title | varchar |
| body | text |
| entity_type | varchar, null |
| entity_id | bigint, null |
| is_read | boolean |
| created_at | timestamptz |
| read_at | timestamptz, null |

### notification_deliveries
| Field | Type |
|---|---|
| PK id | bigint |
| FK notification_id | notifications.id |
| channel | in_app/email |
| status | queued/sent/failed/bounced |
| provider_message_id | varchar, null |
| error | text, null |
| attempt_count | int, default 0 |
| last_attempt_at | timestamptz, null |
| next_attempt_at | timestamptz, null |
| sent_at | timestamptz, null |
| bounced_at | timestamptz, null |
| created_at | timestamptz |
| **UQ** | notification + channel |

### email_verification_tokens
| Field | Type |
|---|---|
| PK id | bigint |
| FK user_id | users.id |
| token_hash | varchar |
| expires_at | timestamptz |
| consumed_at | timestamptz, null |
| created_at | timestamptz |

---

## Chat & 2-Hour Email Digests

### chat_conversations
| Field | Type |
|---|---|
| PK id | bigint |
| type | employee_tl/department_group/direct_tl/all_tls/manager_tls |
| FK department_id | departments.id, null |
| title | varchar, null |
| created_at | timestamptz |

### chat_members
| Field | Type |
|---|---|
| PK id | bigint |
| FK conversation_id | chat_conversations.id |
| FK user_id | users.id |
| joined_at | timestamptz |
| left_at | timestamptz, null |
| **UQ** | conversation + user |

### chat_messages
| Field | Type |
|---|---|
| PK id | bigint |
| FK conversation_id | chat_conversations.id |
| FK sender_id | users.id |
| body | text, null |
| link_url | text, null |
| created_at | timestamptz |
| deleted_at | timestamptz, null |
| FK deleted_by | users.id, null |
| **CHECK** | active message needs body/link; deleted message has both cleared |

### chat_digest_batches
| Field | Type |
|---|---|
| PK id | bigint |
| FK user_id | users.id |
| FK notification_id | notifications.id, null |
| window_start | timestamptz |
| window_end | timestamptz |
| message_count | int |
| status | queued/sent/failed |
| sent_at | timestamptz, null |
| error | text, null |
| created_at | timestamptz |

### chat_digest_batch_messages
| Field | Type |
|---|---|
| PK/FK batch_id | chat_digest_batches.id |
| PK/FK message_id | chat_messages.id |
| added_at | timestamptz |

---

## Performance & Audit

### monthly_performance_snapshots
| Field | Type |
|---|---|
| PK id | bigint |
| FK user_id | users.id, null |
| FK department_id | departments.id, null |
| month_start | date |
| due_steps | int |
| on_time_steps | int |
| overdue_steps | int |
| score | numeric(5,2), null |
| snapshot_type | employee/tl_personal/tl_team/department |
| calculated_at | timestamptz |
| **CHECK** | user/department required by snapshot type |
| **UQ** | separate user and department indexes |

### audit_logs
| Field | Type |
|---|---|
| PK id | bigint |
| FK actor_user_id | users.id, null |
| action | varchar |
| entity_type | varchar |
| entity_id | bigint, null |
| metadata | jsonb |
| ip_address | inet, null |
| created_at | timestamptz |
| **RULE** | append-only |
