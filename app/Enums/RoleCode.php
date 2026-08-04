<?php

namespace App\Enums;

/** The four static roles — BRD §5. No dynamic permission engine (Q2 default). */
enum RoleCode: string
{
    case Admin = 'admin';
    case Manager = 'manager';
    case TeamLeader = 'tl';
    case Employee = 'employee';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Manager => 'Manager',
            self::TeamLeader => 'Team Leader',
            self::Employee => 'Employee',
        };
    }
}
