<?php

namespace App\Enums;

/**
 * BRD §11.1 — the in-app notification is MANDATORY and is the official record; email is a
 * best-effort assisting channel whose failure never blocks a workflow transition (§22.19).
 * A future channel is a new case here plus a new delivery row — never a change to
 * `notifications`, which stores the logical event exactly once.
 */
enum NotificationChannel: string
{
    case InApp = 'in_app';
    case Email = 'email';

    /** The user cannot switch this channel off (BRD §11.1). */
    public function isMandatory(): bool
    {
        return $this === self::InApp;
    }
}
