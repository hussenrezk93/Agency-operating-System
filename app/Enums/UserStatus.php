<?php

namespace App\Enums;

/**
 * users.status — ERD v1.2. Accounts are deactivated, never deleted (BRD §6, §18).
 * APPROVED DECISION Q14: an `on_leave` user still signs in and reads permitted data.
 * A primary Team Leader on leave is covered by a temporary leader and becomes view-only
 * (User::isViewOnlyLeader()); the status itself never blocks authentication.
 */
enum UserStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case OnLeave = 'on_leave';
}
