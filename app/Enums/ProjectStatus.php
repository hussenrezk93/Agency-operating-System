<?php

namespace App\Enums;

/**
 * projects.status.
 * BRD §7.2 defines Active / Completed / Cancelled.
 * `OnHold` is added by APPROVED CHANGE REQUEST Q23 (see CHANGE-REQUEST-REGISTER.md).
 * The STATUS exists now; project-level pausing BEHAVIOR (task pausing, deadline
 * accounting, resume) is deferred to Phase 1B and is implemented nowhere.
 */
enum ProjectStatus: string
{
    case Active = 'active';
    case OnHold = 'on_hold';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /** A closed project can never be reopened (BRD §7.2, Q22). */
    public function isClosed(): bool
    {
        return $this === self::Completed || $this === self::Cancelled;
    }

    /** Only an active project accepts new work. */
    public function acceptsNewTasks(): bool
    {
        return $this === self::Active;
    }
}
