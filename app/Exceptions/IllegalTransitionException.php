<?php

namespace App\Exceptions;

use App\Enums\TaskLifecycle;
use App\Enums\WorkflowStatus;

class IllegalTransitionException extends WorkflowException
{
    public static function between(WorkflowStatus $from, WorkflowStatus $to): self
    {
        return new self("Workflow status cannot move from {$from->value} to {$to->value}.");
    }

    /** @param array<int, WorkflowStatus> $expected */
    public static function wrongStatus(WorkflowStatus $actual, array $expected): self
    {
        $values = implode(', ', array_map(
            static fn (WorkflowStatus $status): string => $status->value,
            $expected,
        ));

        return new self("This action requires one of [{$values}], but the step is {$actual->value}.");
    }

    public static function notFinalStep(int $attemptedSequence, int $lastSequence): self
    {
        return new self(
            "Step {$attemptedSequence} cannot complete the task; the final step is {$lastSequence}.",
        );
    }

    public static function taskClosed(TaskLifecycle $status): self
    {
        return new self("The task is {$status->value} and is read-only.");
    }

    public static function taskOnHold(): self
    {
        return new self('The task is on hold and cannot advance.');
    }

    public static function taskNotOnHold(): self
    {
        return new self('The task is not on hold and cannot be resumed.');
    }

    public static function outputNotRemovable(): self
    {
        return new self('This output link has already been removed or finalized and cannot be removed.');
    }
}
