<?php

namespace App\Enums;

/**
 * department_leadership_assignments.activation_state — APPROVED DECISION Q13.
 * A future-dated temporary assignment is created `Pending`, becomes `Active` on its
 * start date and `Ended` on its end date, without any manual step.
 */
enum ActivationState: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Ended = 'ended';
}
