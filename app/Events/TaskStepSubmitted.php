<?php

namespace App\Events;

use App\Models\TaskStep;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaskStepSubmitted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly TaskStep $step,
        public readonly int $submissionNo,
    ) {}
}
