<?php

namespace App\Enums;

/**
 * payroll_periods.status — a month the Manager opened for one person is either still
 * being worked on (Open, totals read live) or paid and frozen (Closed, totals read from
 * the row's own snapshot).
 */
enum PayrollStatus: string
{
    case Open = 'open';
    case Closed = 'closed';

    public function isClosed(): bool
    {
        return $this === self::Closed;
    }

    /** The badge class the payroll table shows for this status. */
    public function badgeClass(): string
    {
        return $this === self::Closed ? 'b-approved' : 'b-progress';
    }
}
