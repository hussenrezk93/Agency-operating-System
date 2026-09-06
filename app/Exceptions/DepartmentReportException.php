<?php

namespace App\Exceptions;

class DepartmentReportException extends WorkflowException
{
    public static function alreadySubmitted(): self
    {
        return new self('This report has already been submitted.');
    }

    public static function notYetSubmitted(): self
    {
        return new self('This report has not been submitted yet — nothing to edit.');
    }

    public static function alreadyApproved(): self
    {
        return new self('This report has already been approved.');
    }
}
