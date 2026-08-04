<?php

namespace App\Events;

use App\Models\TaskStep;
use App\Models\TaskStepAssignment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaskStepAssigned
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly TaskStep $step,
        public readonly TaskStepAssignment $assignment,
    ) {}
}
