<?php

namespace App\Enums;

/**
 * BRD §14 — the five conversation shapes, plus `Direct`: a deliberate, later-approved
 * extension for open person-to-person messaging (including Admin) via the company
 * directory. See ChatPolicy's class doc for how Admin's access stays narrowly scoped.
 */
enum ConversationType: string
{
    case EmployeeTl = 'employee_tl';
    case DepartmentGroup = 'department_group';
    case DirectTl = 'direct_tl';
    case AllTls = 'all_tls';
    case ManagerTls = 'manager_tls';
    case Direct = 'direct';

    /** Only a department group belongs to a department (enforced by a CHECK too). */
    public function requiresDepartment(): bool
    {
        return $this === self::DepartmentGroup;
    }
}
