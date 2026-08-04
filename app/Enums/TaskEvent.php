<?php

namespace App\Enums;

/**
 * task_status_history.event_type — every entry the task timeline must be able to show
 * (BRD §9, §19). One vocabulary shared by the timeline, the audit log and the UI.
 */
enum TaskEvent: string
{
    case Created = 'created';
    case SentToDepartment = 'sent_to_department';
    case Assigned = 'assigned';
    case Reassigned = 'reassigned';
    case FirstSeen = 'first_seen';
    case OutputAdded = 'output_added';
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
}
