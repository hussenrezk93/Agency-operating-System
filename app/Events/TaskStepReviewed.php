<?php

namespace App\Events;

use App\Enums\WorkflowStatus;
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
        /** The status the step transitioned FROM — lets the listener tell the TL-stage
         *  decision (UnderReview) apart from the Manager-stage one (PendingManagerReview)
         *  without re-deriving it from the step's now-mutated current state. */
        public readonly WorkflowStatus $from,
    ) {}
}
