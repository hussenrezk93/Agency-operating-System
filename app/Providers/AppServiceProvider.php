<?php

namespace App\Providers;

use App\Models\AuditLog;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Client;
use App\Models\Department;
use App\Models\DepartmentDailyReport;
use App\Models\DepartmentLeadershipAssignment;
use App\Models\DepartmentOutputAccess;
use App\Models\DepartmentRoute;
use App\Models\EmployeeSalary;
use App\Models\Expense;
use App\Models\Notification;
use App\Models\PayrollPeriod;
use App\Models\PerformanceAdjustment;
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
use App\Policies\DepartmentReportPolicy;
use App\Policies\DepartmentRoutePolicy;
use App\Policies\NotificationPolicy;
use App\Policies\PayrollPolicy;
use App\Policies\PerformanceAdjustmentPolicy;
use App\Policies\ProjectPolicy;
use App\Policies\TaskPolicy;
use App\Policies\TaskStepPolicy;
use App\Policies\TemporaryLeadershipPolicy;
use App\Policies\UserPolicy;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
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
        Gate::policy(DepartmentDailyReport::class, DepartmentReportPolicy::class);
        Gate::policy(PerformanceAdjustment::class, PerformanceAdjustmentPolicy::class);
        // One Manager-only rule covers all three payroll models, so one policy class
        // answers for all three rather than three copies of the same method.
        Gate::policy(EmployeeSalary::class, PayrollPolicy::class);
        Gate::policy(PayrollPeriod::class, PayrollPolicy::class);
        Gate::policy(Expense::class, PayrollPolicy::class);

        // MySQL's own CURRENT_TIMESTAMP/useCurrent() defaults run on the session's
        // time_zone, not on 'app.timezone' — every model with standard Eloquent
        // $timestamps writes now() explicitly from PHP so those are Africa/Cairo-correct
        // regardless, but a table that leaves created_at to the DB default alone (e.g.
        // AuditLog: $timestamps=false, so nothing in PHP ever sets it — this is the
        // reported "log timestamp is wrong" case) silently used the MySQL SERVER's own
        // timezone instead. This mirrors that offset onto the session on every new
        // connection, computed fresh from PHP's own current app.timezone offset — not a
        // fixed value in config/database.php, because this host's MySQL doesn't have the
        // mysql.time_zone_name tables loaded (a named zone there fails to connect
        // outright) and a hardcoded numeric offset would go stale the moment the
        // computed Cairo offset ever shifts.
        Event::listen(ConnectionEstablished::class, function (ConnectionEstablished $event): void {
            if ($event->connection->getDriverName() === 'mysql') {
                $event->connection->statement('SET time_zone = ?', [now()->format('P')]);
            }
        });
    }
}
