<?php

namespace App\Enums;

/** department_daily_reports.type — the auto-filled task-log report every department
 *  gets, versus Content's second, blank report addressed to Moderator. */
enum DepartmentReportType: string
{
    case Summary = 'summary';
    case ModeratorHandoff = 'moderator_handoff';
}
