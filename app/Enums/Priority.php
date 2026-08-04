<?php

namespace App\Enums;

/** tasks.priority — Urgent sorts to the top of every list (BRD §8, §16). */
enum Priority: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Urgent = 'urgent';

    /** Lower number sorts first. */
    public function weight(): int
    {
        return match ($this) {
            self::Urgent => 0,
            self::High => 1,
            self::Medium => 2,
            self::Low => 3,
        };
    }
}
