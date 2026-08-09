<?php

namespace App\Providers;

use App\Models\AuditLog;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Client;
use App\Models\Department;
use App\Models\DepartmentLeadershipAssignment;
use App\Models\DepartmentOutputAccess;
use App\Models\DepartmentRoute;
use App\Models\Notification;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskStep;
use App\Models\User;
use App\Policies\AuditLogPolicy;
use App\Policies\ChatMessagePolicy;
use App\Policies\ChatPolicy;
use App\Policies\ClientPolicy;
use App\Policies\DepartmentOutputAccessPolicy;
use App\Policies\DepartmentPolicy;
use App\Policies\DepartmentRoutePolicy;
use App\Policies\NotificationPolicy;
use App\Policies\ProjectPolicy;
use App\Policies\TaskPolicy;
use App\Policies\TaskStepPolicy;
use App\Policies\TemporaryLeadershipPolicy;
use App\Policies\UserPolicy;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // The framework's built-in pagination views assume Tailwind/Bootstrap is
        // loaded; this app ships neither, so they render broken (see
        // resources/views/vendor/pagination/agencyos.blade.php for why).
        Paginator::defaultView('pagination::agencyos');
        Paginator::defaultSimpleView('pagination::agencyos');

        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Department::class, DepartmentPolicy::class);
        Gate::policy(Project::class, ProjectPolicy::class);
        Gate::policy(AuditLog::class, AuditLogPolicy::class);
        Gate::policy(DepartmentLeadershipAssignment::class, TemporaryLeadershipPolicy::class);
        Gate::policy(Task::class, TaskPolicy::class);
        Gate::policy(TaskStep::class, TaskStepPolicy::class);
        Gate::policy(DepartmentRoute::class, DepartmentRoutePolicy::class);
        Gate::policy(DepartmentOutputAccess::class, DepartmentOutputAccessPolicy::class);
        Gate::policy(Client::class, ClientPolicy::class);
        Gate::policy(Notification::class, NotificationPolicy::class);
        Gate::policy(ChatConversation::class, ChatPolicy::class);
        Gate::policy(ChatMessage::class, ChatMessagePolicy::class);
    }
}
