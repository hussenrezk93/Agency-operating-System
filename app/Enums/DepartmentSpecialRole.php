<?php

namespace App\Enums;

/**
 * departments.special_role — a stable identifier for the handful of departments the
 * Daily Department Reports feature needs to single out (Content's extra report,
 * Moderator as its recipient, Photography/Videography's extended deadline), independent
 * of the department's freely Admin-editable `name`. Set once via the department
 * edit form; every other department stays null.
 */
enum DepartmentSpecialRole: string
{
    case Content = 'content';
    case Moderator = 'moderator';
    case PhotographyVideography = 'photography_videography';
    /** Product decision 2026-09 — Graphic's work passes through Content's review
     *  before it ever reaches the Manager (see WorkflowStatus::PendingContentReview). */
    case Graphic = 'graphic';
    /** Product decision 2026-09 — Sales writes its daily report collectively: every
     *  member types their own part and the system stacks them into the one report
     *  (see DepartmentReportService::composeDetails()). */
    case Sales = 'sales';

    public function label(): string
    {
        return match ($this) {
            self::Content => 'Content',
            self::Moderator => 'Moderator',
            self::PhotographyVideography => 'Photography/Videography',
            self::Graphic => 'Graphic',
            self::Sales => 'Sales',
        };
    }
}
