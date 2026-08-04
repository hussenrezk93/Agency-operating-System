<?php

namespace App\Events;

use App\Models\Task;
use App\Models\TaskStep;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaskRedirected
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Task $task,
        public readonly TaskStep $fromStep,
        public readonly TaskStep $toStep,
    ) {}
}
