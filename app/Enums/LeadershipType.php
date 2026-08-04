<?php

namespace App\Enums;

/**
 * department_leadership_assignments.assignment_type — ERD v1.2.
 * Appointment, scheduling, replacement and role transition are implemented
 * (approved decisions Q2, Q3, Q6, Q8, Q9, Q13, Q16). The TASK-TRANSFER part of the
 * handover (Q10) is deferred to Phase 1B because it requires the task tables.
 */
enum LeadershipType: string
{
    case Primary = 'primary';
    case Temporary = 'temporary';
}
