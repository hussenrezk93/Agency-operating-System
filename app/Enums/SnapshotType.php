<?php

namespace App\Enums;

/**
 * BRD §17 — the four monthly views. `TlPersonal` and `TlTeam` are separate on purpose: a
 * Team Leader's own delivery must never be blended with their department's, so the report
 * shows the two side by side rather than as one misleading number.
 */
enum SnapshotType: string
{
    case Employee = 'employee';
    case TlPersonal = 'tl_personal';
    case TlTeam = 'tl_team';
    case Department = 'department';

    /** A department snapshot names a department; every other kind names a user. */
    public function isDepartmentLevel(): bool
    {
        return $this === self::Department;
    }
}
