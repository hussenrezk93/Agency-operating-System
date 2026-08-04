<?php

namespace App\Enums;

/** task_step_reviews.decision — a comment is mandatory for ChangesRequested (BRD §9.7). */
enum ReviewDecision: string
{
    case Approved = 'approved';
    case ChangesRequested = 'changes_requested';
}
