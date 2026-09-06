<?php

namespace App\Enums;

/**
 * task_status_history.event_type — every entry the task timeline must be able to show
 * (BRD §9, §19). One vocabulary shared by the timeline, the audit log and the UI.
 */
enum TaskEvent: string
{
    case Created = 'created';
    case Published = 'published';
    case SentToDepartment = 'sent_to_department';
    case Assigned = 'assigned';
    case Reassigned = 'reassigned';
    case FirstSeen = 'first_seen';
    case OutputAdded = 'output_added';
    case OutputRemoved = 'output_removed';
    case Submitted = 'submitted';
    case ChangesRequested = 'changes_requested';
    case Resubmitted = 'resubmitted';
    case Approved = 'approved';
    case Transferred = 'transferred';
    case Completed = 'completed';
    case OnHold = 'on_hold';
    case Resumed = 'resumed';
    case Redirected = 'redirected';
    case Cancelled = 'cancelled';
    case DeadlineChanged = 'deadline_changed';
    case ContentApproved = 'content_approved';
    case ContentChangesRequested = 'content_changes_requested';
    case ManagerApproved = 'manager_approved';
    case ManagerChangesRequested = 'manager_changes_requested';
    case TaskReopened = 'task_reopened';

    /**
     * Read-only leftovers from the removed "creator review" feature (CR-003, 2026-09).
     * Nothing writes these anymore, but pre-existing task_status_history rows still
     * carry them — the enum cast throws a ValueError on any value it can't map, so
     * deleting these cases 500'd every task whose timeline included one (found in
     * production 2026-09-02: real rows with event_type=creator_review_rejected).
     * Keep them here purely so old rows still deserialize; do not use in new code.
     */
    case CreatorReviewRequested = 'creator_review_requested';
    case CreatorReviewApproved = 'creator_review_approved';
    case CreatorReviewRejected = 'creator_review_rejected';
}
