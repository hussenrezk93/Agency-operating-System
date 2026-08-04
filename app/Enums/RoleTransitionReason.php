<?php

namespace App\Enums;

/** Why an effective role changed — APPROVED DECISIONS Q2, Q8, Q16. */
enum RoleTransitionReason: string
{
    case TemporaryTlStart = 'temporary_tl_start';
    case TemporaryTlEnd = 'temporary_tl_end';
    case TemporaryTlEarlyEnd = 'temporary_tl_early_end';
    case TemporaryTlReplaced = 'temporary_tl_replaced';
}
