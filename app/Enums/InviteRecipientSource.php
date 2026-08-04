<?php

namespace App\Enums;

/**
 * BRD §7.3 — WHY a person is a member of the project, which decides whether they keep the
 * invite when a department is removed. Membership derived from a department ends with it;
 * the creator and the Manager keep theirs.
 */
enum InviteRecipientSource: string
{
    case Department = 'department';
    case Creator = 'creator';
    case Manager = 'manager';
    case TemporaryTl = 'temp_tl';

    public function endsWithDepartment(): bool
    {
        return $this === self::Department || $this === self::TemporaryTl;
    }
}
