<?php

namespace App\Events;

use App\Models\Task;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaskHeld
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly Task $task) {}
}
