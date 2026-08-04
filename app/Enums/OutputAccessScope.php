<?php

namespace App\Enums;

/** department_output_access.scope — BRD §15 + APPROVED DECISION Q25. */
enum OutputAccessScope: string
{
    case AllOutputs = 'all_outputs';
    case FinalOnly = 'final_only';
}
