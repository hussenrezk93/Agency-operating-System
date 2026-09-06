<?php

namespace App\Enums;

/** performance_adjustments.type — money added to, or taken off, one person's month. */
enum AdjustmentType: string
{
    case Bonus = 'bonus';
    case Deduction = 'deduction';

    /** The badge class each type carries on the reports table. */
    public function badgeClass(): string
    {
        return $this === self::Bonus ? 'b-approved' : 'b-overdue';
    }

    /** Signed multiplier for a running total — a deduction subtracts. */
    public function sign(): int
    {
        return $this === self::Bonus ? 1 : -1;
    }
}
