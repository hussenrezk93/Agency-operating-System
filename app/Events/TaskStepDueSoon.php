<?php

namespace App\Events;

use App\Models\TaskStep;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaskStepDueSoon
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly TaskStep $step) {}
}
