<?php

use App\Http\Controllers\ApprovedUiController;
use App\Http\Controllers\Auth\ForcedPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\DepartmentOutputAccessController;
use App\Http\Controllers\DepartmentRoutingController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MyTasksController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PerformanceController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectDepartmentController;
use App\Http\Controllers\ProjectLinkController;
use App\Http\Controllers\ProjectWhatsappController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TaskStepController;
use App\Http\Controllers\TemporaryLeadershipController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
| Phase 1A routes — authentication + a minimal protected surface that exists
| purely so the middleware/policy spine is testable. Feature routes arrive with
| their phases. /up (framework health endpoint) is configured in bootstrap/app.php.
*/

/*
| The project root always hands off to the Laravel login route. An already
| authenticated visitor is NOT logged out: `login` carries the `guest` middleware,
| whose defaultRedirectUri() finds the named `dashboard` route and forwards them
| there. One hop, no loop, and a single source of truth for where signing in
| begins. The approved prototype's own login.html is never served (see
| ApprovedUiController, which redirects it to the caller's role dashboard).
*/
Route::get('/', fn () => redirect()->route('login'))->name('root');

Route::post('/locale', LocaleController::class)->name('locale.update');

// BRD §18.1 — unauthenticated on purpose; the token proves the click, not the session.
// Throttled defense-in-depth against token-guessing, even though a 40-char random token
// is already computationally infeasible to brute force.
Route::get('/email/verify/{user}/{token}', [EmailVerificationController::class, 'verify'])
    ->middleware('throttle:10,1')
    ->name('email.verify');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

Route::middleware(['auth', 'account.active'])->group(function (): void {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/password/forced', [ForcedPasswordController::class, 'show'])->name('password.forced');
    Route::post('/password/forced', [ForcedPasswordController::class, 'store'])->name('password.forced.store');

    Route::middleware('password.changed')->group(function (): void {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');

        // BRD §18.1 — every role edits their own personal email here; no {user} route
        // parameter, so there is nothing to authorize beyond "is authenticated."
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');

        // Approved 51-screen UI, now protected by Laravel auth and server-side role checks.
        Route::get('/app/{screen?}', ApprovedUiController::class)
            ->where('screen', '.*')
            ->name('approved-ui');

        /*
         | Temporary Team Leader administration — MANAGER ONLY (approved decisions
         | Q2, Q8, Q13, Q16, Q18). Three independent layers guard these endpoints:
         | route middleware `role:manager`, FormRequest::authorize(), and an explicit
         | $this->authorize() call in the controller against TemporaryLeadershipPolicy.
         */
        Route::middleware('role:manager')->prefix('temporary-leadership')->group(function (): void {
            Route::get('/', [TemporaryLeadershipController::class, 'index'])
                ->name('temporary-leadership.index');
            Route::get('/create', [TemporaryLeadershipController::class, 'create'])
                ->name('temporary-leadership.create-form');
            Route::post('/', [TemporaryLeadershipController::class, 'store'])
                ->name('temporary-leadership.store');
            Route::post('/{assignment}/replace', [TemporaryLeadershipController::class, 'replace'])
                ->name('temporary-leadership.replace');
            Route::post('/{assignment}/end', [TemporaryLeadershipController::class, 'endEarly'])
                ->name('temporary-leadership.end');
        });

        /*
         | Phase 3 — Users & Departments administration, plus the Admin-owned
         | permission matrices (BRD §15). Route middleware is the OUTER, coarse
         | layer (excludes roles that can never touch this resource at all);
         | UserPolicy/DepartmentPolicy/DepartmentRoutePolicy/DepartmentOutputAccessPolicy
         | make the fine-grained call inside each FormRequest::authorize() and
         | controller authorize() call — e.g. an Admin passes the route middleware
         | on /users but is still refused creating anything except a Manager.
         */
        Route::middleware('role:admin,manager')->prefix('users')->group(function (): void {
            Route::get('/', [UserController::class, 'index'])->name('users.index');
            Route::get('/create', [UserController::class, 'create'])->name('users.create-form');
            Route::get('/{user}/edit', [UserController::class, 'edit'])->name('users.edit-form');
            Route::post('/', [UserController::class, 'store'])->name('users.store');
            Route::patch('/{user}', [UserController::class, 'update'])->name('users.update');
            Route::post('/{user}/disable', [UserController::class, 'disable'])->name('users.disable');
            Route::post('/{user}/reactivate', [UserController::class, 'reactivate'])->name('users.reactivate');
            Route::post('/{user}/reset-password', [UserController::class, 'resetPassword'])
                ->name('users.reset-password');
        });

        Route::middleware('role:admin,manager')->prefix('departments')->group(function (): void {
            Route::get('/', [DepartmentController::class, 'index'])->name('departments.index');
            // Create/update/deactivate/reactivate are Manager-only (DepartmentPolicy) —
            // Admin passes this coarse gate for viewAny only.
            Route::get('/create', [DepartmentController::class, 'create'])->name('departments.create-form');
            Route::get('/{department}/edit', [DepartmentController::class, 'edit'])->name('departments.edit-form');
            Route::post('/', [DepartmentController::class, 'store'])->name('departments.store');
            Route::patch('/{department}', [DepartmentController::class, 'update'])->name('departments.update');
            Route::post('/{department}/deactivate', [DepartmentController::class, 'deactivate'])
                ->name('departments.deactivate');
            Route::post('/{department}/reactivate', [DepartmentController::class, 'reactivate'])
                ->name('departments.reactivate');
        });

        // Admin-only (BRD §15): the routing matrix TaskRoutingService reads from.
        Route::middleware('role:admin')->prefix('department-routes')->group(function (): void {
            Route::get('/', [DepartmentRoutingController::class, 'index'])->name('department-routes.index');
            Route::post('/', [DepartmentRoutingController::class, 'upsert'])->name('department-routes.upsert');
        });

        // Admin-only (BRD §15 "قاعدة مشاهدة المخرجات"): cross-department output visibility.
        Route::middleware('role:admin')->prefix('department-output-access')->group(function (): void {
            Route::get('/', [DepartmentOutputAccessController::class, 'index'])
                ->name('department-output-access.index');
            Route::post('/', [DepartmentOutputAccessController::class, 'upsert'])
                ->name('department-output-access.upsert');
        });

        /*
         | Phase 4 — Clients, Projects & the WhatsApp invite ledger (BRD §7).
         | ClientPolicy/ProjectPolicy exclude Admin entirely (configuration/audit
         | only, BRD §15) and exclude Employee from every mutating action; Employee
         | keeps read access to projects their own department participates in
         | (ProjectPolicy::view), which is why the read routes carry a wider
         | role list than the write routes.
         */
        Route::middleware('role:manager,tl')->prefix('clients')->group(function (): void {
            Route::get('/', [ClientController::class, 'index'])->name('clients.index');
            // /create and /{client}/edit are Blade-only (Phase 5-style real screens).
            Route::get('/create', [ClientController::class, 'create'])->name('clients.create-form');
            Route::get('/{client}/edit', [ClientController::class, 'edit'])->name('clients.edit-form');
            Route::post('/', [ClientController::class, 'store'])->name('clients.store');
            Route::patch('/{client}', [ClientController::class, 'update'])->name('clients.update');
            Route::post('/{client}/deactivate', [ClientController::class, 'deactivate'])
                ->name('clients.deactivate');
            Route::post('/{client}/reactivate', [ClientController::class, 'reactivate'])
                ->name('clients.reactivate');
        });

        Route::prefix('projects')->group(function (): void {
            // /create MUST be registered before the {project}-bound routes below, or
            // "create" is captured as a project ID (same reasoning as tasks.create-form).
            Route::middleware('role:manager,tl')->group(function (): void {
                Route::get('/create', [ProjectController::class, 'create'])->name('projects.create-form');
            });

            Route::middleware('role:manager,tl,employee')->group(function (): void {
                Route::get('/', [ProjectController::class, 'index'])->name('projects.index');
                Route::get('/{project}', [ProjectController::class, 'show'])->name('projects.show');
                Route::get('/{project}/whatsapp', [ProjectWhatsappController::class, 'index'])
                    ->name('projects.whatsapp.index');
            });

            Route::middleware('role:manager,tl')->group(function (): void {
                Route::post('/', [ProjectController::class, 'store'])->name('projects.store');
                Route::patch('/{project}', [ProjectController::class, 'update'])->name('projects.update');
                Route::post('/{project}/complete', [ProjectController::class, 'complete'])
                    ->name('projects.complete');
                Route::post('/{project}/cancel', [ProjectController::class, 'cancel'])
                    ->name('projects.cancel');
                Route::post('/{project}/hold', [ProjectController::class, 'hold'])->name('projects.hold');
                Route::post('/{project}/resume', [ProjectController::class, 'resume'])->name('projects.resume');

                Route::post('/{project}/departments', [ProjectDepartmentController::class, 'store'])
                    ->name('projects.departments.store');
                Route::delete('/{project}/departments/{department}', [ProjectDepartmentController::class, 'destroy'])
                    ->name('projects.departments.destroy');

                Route::post('/{project}/links', [ProjectLinkController::class, 'store'])
                    ->name('projects.links.store');

                Route::post('/{project}/whatsapp', [ProjectWhatsappController::class, 'store'])
                    ->name('projects.whatsapp.store');
                Route::delete('/{project}/whatsapp', [ProjectWhatsappController::class, 'destroy'])
                    ->name('projects.whatsapp.destroy');
            });
        });

        /*
        |------------------------------------------------------------------------
        | PHASE 1B SLICE 2 — task workflow engine (backend only, JSON).
        |------------------------------------------------------------------------
        | The role middleware below is the OUTERMOST of three layers, not the whole
        | rule: it rejects roles that can never perform the action at all, then
        | FormRequest::authorize() and the service's own Gate call decide the case at
        | hand (right department, right step status, effective leader, self-assigned).
        |
        | Note which role is absent from each line — that is where the approved
        | decisions live:
        |   · assign/reassign  exclude the MANAGER entirely (Q21: only the effective
        |     Team Leader of the receiving department picks the person and the dates).
        |   · transfer         excludes the Manager (their tool is Redirect, BRD §10).
        |   · review           includes the Manager because Q12 makes them the reviewer
        |     of a step the Team Leader self-assigned; the policy allows them ONLY in
        |     that case.
        |   · every task route excludes the ADMIN (BRD §15: configuration and audit
        |     only, never task content).
        */
        Route::prefix('tasks')->group(function (): void {
            // Phase 5 — real Blade screens. /create MUST be registered before the
            // {task}-bound routes below, or "create" is captured as a task ID.
            Route::middleware('role:employee,tl,manager')->group(function (): void {
                Route::get('/', [TaskController::class, 'index'])->name('tasks.index');
            });
            Route::middleware('role:manager,tl')->group(function (): void {
                Route::get('/create', [TaskController::class, 'create'])->name('tasks.create-form');
            });

            Route::middleware('role:employee,tl,manager')->group(function (): void {
                Route::get('/mine', [MyTasksController::class, 'index'])->name('tasks.mine');
                Route::get('/{task}', [TaskController::class, 'show'])->name('tasks.show');
                Route::get('/{task}/history', [TaskController::class, 'history'])->name('tasks.history');
            });

            Route::middleware('role:manager,tl')->group(function (): void {
                Route::post('/', [TaskController::class, 'store'])->name('tasks.store');
                Route::get('/{task}/edit', [TaskController::class, 'edit'])->name('tasks.edit-form');
                Route::patch('/{task}', [TaskController::class, 'update'])->name('tasks.update');
                Route::post('/{task}/publish', [TaskController::class, 'publish'])->name('tasks.publish');
                Route::delete('/{task}', [TaskController::class, 'destroy'])->name('tasks.destroy');
                Route::post('/{task}/cancel', [TaskController::class, 'cancel'])->name('tasks.cancel');
                Route::post('/{task}/hold', [TaskController::class, 'hold'])->name('tasks.hold');
                Route::post('/{task}/resume', [TaskController::class, 'resume'])->name('tasks.resume');
            });

            // Manager-only — Redirect bypasses the routing matrix (BRD §10); a TL's
            // correction tool is Transfer, the same way task-steps' transfer route
            // excludes the Manager.
            Route::middleware('role:manager')->group(function (): void {
                Route::post('/{task}/redirect', [TaskController::class, 'redirect'])->name('tasks.redirect');
            });
        });

        Route::prefix('task-steps')->group(function (): void {
            // A Team Leader appears here too — comments are a private thread between
            // the assignee and the department's effective Team Leader (BRD §13).
            Route::middleware('role:employee,tl')->group(function (): void {
                Route::post('/{step}/comments', [TaskStepController::class, 'addComment'])
                    ->name('tasks.steps.comments.store');
            });

            // Q21 — the Manager is not on this line by design.
            Route::middleware('role:tl')->group(function (): void {
                Route::post('/{step}/assign', [TaskStepController::class, 'assign'])
                    ->name('tasks.steps.assign');
                Route::post('/{step}/reassign', [TaskStepController::class, 'reassign'])
                    ->name('tasks.steps.reassign');
                Route::post('/{step}/transfer', [TaskStepController::class, 'transfer'])
                    ->name('tasks.steps.transfer');
                Route::get('/{step}/allowed-departments', [TaskStepController::class, 'allowedDepartments'])
                    ->name('tasks.steps.allowed-departments');
                // Q20 — the effective Team Leader of the department, nobody else.
                Route::get('/{step}/first-seen', [TaskStepController::class, 'firstSeen'])
                    ->name('tasks.steps.first-seen');
            });

            // A Team Leader appears here because a self-assigned step is executed by them.
            Route::middleware('role:employee,tl')->group(function (): void {
                Route::post('/{step}/outputs', [TaskStepController::class, 'addOutput'])
                    ->name('tasks.steps.outputs.store');
                Route::post('/{step}/submit', [TaskStepController::class, 'submit'])
                    ->name('tasks.steps.submit');
            });

            // Q12 — the Manager reviews a self-assigned Team Leader step, and only that.
            Route::middleware('role:tl,manager')->group(function (): void {
                Route::post('/{step}/review', [TaskStepController::class, 'review'])
                    ->name('tasks.steps.review');
                Route::post('/{step}/complete', [TaskStepController::class, 'complete'])
                    ->name('tasks.steps.complete');
            });
        });

        Route::prefix('notifications')->group(function (): void {
            Route::get('/', [NotificationController::class, 'index'])->name('notifications.index');
            Route::post('/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
        });

        Route::post('/email/resend-verification', [EmailVerificationController::class, 'resend'])
            ->middleware('throttle:3,1')
            ->name('email.resend-verification');

        // Admin can reach chat for the company directory and general direct messages only —
        // ChatPolicy's membership check keeps them out of the five BRD §14/§19-restricted
        // conversation types, since ChatService never makes Admin a member of one.
        Route::prefix('chat')->middleware('role:employee,tl,manager,admin')->group(function (): void {
            Route::get('/', [ChatController::class, 'index'])->name('chat.index');
            Route::get('/{conversation}', [ChatController::class, 'show'])->name('chat.show');
            Route::post('/{conversation}/messages', [ChatController::class, 'store'])->name('chat.messages.store');
            Route::post('/messages/{message}/delete', [ChatController::class, 'destroy'])->name('chat.messages.destroy');
            Route::post('/direct', [ChatController::class, 'startDirect'])->name('chat.direct');
            Route::post('/direct-message', [ChatController::class, 'startDirectMessage'])->name('chat.direct-message');
        });

        // BRD §17 — reports.index redirects Employee to their own performance page
        // internally; performance.show's {user?} lets self-view stay open to all three
        // ops roles while viewing someone else is gated by UserPolicy::viewPerformance().
        Route::middleware('role:manager,tl,employee')->group(function (): void {
            Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
            Route::get('/performance/{user?}', [PerformanceController::class, 'show'])->name('performance.show');
            Route::get('/search', [SearchController::class, 'index'])->name('search.index');
        });

        // Role-spine smoke routes — one per role, used by RoleMiddlewareTest.
        Route::get('/admin/foundation', fn () => response()->json(['area' => 'admin']))
            ->middleware('role:admin')->name('foundation.admin');
        Route::get('/manager/foundation', fn () => response()->json(['area' => 'manager']))
            ->middleware('role:manager')->name('foundation.manager');
        Route::get('/tl/foundation', fn () => response()->json(['area' => 'tl']))
            ->middleware('role:tl,manager')->name('foundation.tl');
        Route::get('/employee/foundation', fn () => response()->json(['area' => 'employee']))
            ->middleware('role:employee')->name('foundation.employee');
    });
});
