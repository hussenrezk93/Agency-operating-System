<?php

namespace App\Events;

use App\Models\TaskStep;
use App\Models\TaskStepReview;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaskStepReviewed
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly TaskStep $step,
        public readonly TaskStepReview $review,
    ) {}
}
