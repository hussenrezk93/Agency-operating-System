<?php

namespace App\Enums;

/**
 * task_steps.deadline_status — a DERIVED, cached value kept strictly separate from
 * WorkflowStatus (approved decision Q7: Overdue is a deadline state, never a workflow
 * state). `Paused` exists per approved decision Q9 because On Hold stops the clock.
 * Computed in exactly one place: DeadlineService.
 */
enum DeadlineStatus: string
{
    case NotStarted = 'not_started';
    case OnTime = 'on_time';
    case DueSoon = 'due_soon';
    case Overdue = 'overdue';
    case Paused = 'paused';
    case Closed = 'closed';
}
