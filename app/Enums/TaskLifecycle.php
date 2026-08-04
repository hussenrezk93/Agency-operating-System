<?php

namespace App\Enums;

/** tasks.lifecycle_status — the task as a whole (BRD §10). */
enum TaskLifecycle: string
{
    case Draft = 'draft';
    case Active = 'active';
    case OnHold = 'on_hold';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /** Completed and cancelled tasks are read-only forever (BRD §22.6). */
    public function isClosed(): bool
    {
        return $this === self::Completed || $this === self::Cancelled;
    }

    public function isRunning(): bool
    {
        return $this === self::Active;
    }
}
