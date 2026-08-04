<?php

namespace App\Exceptions;

class MissingOutputException extends WorkflowException
{
    public static function forSubmission(int $submissionNo): self
    {
        return new self("Submission {$submissionNo} requires at least one output.");
    }
}
