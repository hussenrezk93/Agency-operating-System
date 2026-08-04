<?php

namespace App\Listeners;

use App\Events\ProjectCancelled;
use App\Services\NotificationService;

/**
 * Appendix B — "Project cancelled" -> participating departments' effective TLs.
 * Cascading to individual task assignees is explicitly deferred (ProjectService's own
 * scope note — cancel() does not yet cascade into child tasks).
 */
class NotifyOnProjectCancelled
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function handle(ProjectCancelled $event): void
    {
        $project = $event->project;

        $leaders = $project->departments()
            ->wherePivot('is_active', true)
            ->get()
            ->map(fn ($department) => $department->effectiveLeader())
            ->filter()
            ->unique('id');

        foreach ($leaders as $leader) {
            $this->notifications->notify(
                $leader,
                'project.cancelled',
                __('agencyos.notifications.messages.project_cancelled_title'),
                __('agencyos.notifications.messages.project_cancelled_body', ['project' => $project->name]),
                'project',
                $project->id,
            );
        }
    }
}
