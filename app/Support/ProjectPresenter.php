<?php

namespace App\Support;

use App\Enums\ProjectStatus;

/**
 * Maps ProjectStatus to the approved prototype's existing badge CSS classes
 * (`public/assets/agencyos.css` — `.badge .b-*`, matching `project-details.html`'s own
 * `PB` map) and a translated label.
 */
final class ProjectPresenter
{
    /** @return array{class: string, label: string} */
    public static function statusBadge(ProjectStatus $status): array
    {
        $class = match ($status) {
            ProjectStatus::Active => 'b-progress',
            ProjectStatus::OnHold => 'b-hold',
            ProjectStatus::Completed => 'b-approved',
            ProjectStatus::Cancelled => 'b-cancel',
        };

        return ['class' => $class, 'label' => __('agencyos.projects.status.'.$status->value)];
    }
}
