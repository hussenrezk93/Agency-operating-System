<?php

namespace App\Exceptions;

use App\Models\Department;

class RoutingNotAllowedException extends WorkflowException
{
    public static function inactiveDepartment(Department $department): self
    {
        return new self("Department '{$department->name}' is inactive and cannot receive work.");
    }

    public static function sameDepartment(Department $department): self
    {
        return new self("A task step cannot be routed back to its current department '{$department->name}'.");
    }

    public static function notPermitted(Department $from, Department $to): self
    {
        return new self("Routing from '{$from->name}' to '{$to->name}' is not allowed.");
    }

    public static function notInProject(Department $department): self
    {
        return new self("Department '{$department->name}' does not participate in this project.");
    }
}
