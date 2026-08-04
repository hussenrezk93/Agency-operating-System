<?php

namespace App\Http\Controllers;

use App\Enums\RoleCode;
use App\Http\Requests\CancelProjectRequest;
use App\Http\Requests\HoldProjectRequest;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Models\Client;
use App\Models\Department;
use App\Models\Project;
use App\Services\ProjectService;
use App\Services\ProjectWhatsappService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Thin controller — validation in Form Requests, business rules in ProjectService. */
class ProjectController extends Controller
{
    public function __construct(
        private readonly ProjectService $service,
        private readonly ProjectWhatsappService $whatsapp,
    ) {}

    public function index(Request $request): JsonResponse|View
    {
        $this->authorize('viewAny', Project::class);

        $user = $request->user();
        $projects = Project::query()
            ->when(! $user->hasRole(RoleCode::Manager), function ($query) use ($user) {
                $query->whereHas('departments', fn ($q) => $q->where('departments.id', $user->department_id));
            })
            ->with(['client:id,name', 'departments:id,name'])
            ->latest('id')
            ->get();

        if (! $request->expectsJson()) {
            return view('projects.index', [
                'projects' => $projects,
                'canCreate' => $user->can('create', Project::class),
            ]);
        }

        return response()->json(['data' => $projects]);
    }

    /** Blade-only — the create-project form. */
    public function create(Request $request): View
    {
        $this->authorize('create', Project::class);
        $actor = $request->user();

        $departments = Department::where('is_active', true)->orderBy('name')->get();

        if ($actor->hasRole(RoleCode::TeamLeader)) {
            $allowed = array_merge(
                [$actor->department_id],
                $actor->department?->allowedNextDepartmentIds() ?? [],
            );
            $departments = $departments->whereIn('id', $allowed)->values();
        }

        return view('projects.create', [
            'departments' => $departments,
            'clients' => Client::where('status', 'active')->orderBy('name')->get(),
        ]);
    }

    public function show(Request $request, Project $project): JsonResponse|View
    {
        $this->authorize('view', $project);

        $project->load(['client', 'departments', 'links', 'creator:id,full_name', 'completedBy:id,full_name', 'cancelledBy:id,full_name']);

        if (! $request->expectsJson()) {
            $actor = $request->user();

            return view('projects.show', [
                'project' => $project,
                'members' => $this->whatsapp->resolveMembers($project),
                'currentLink' => $project->currentWhatsappLinkVersion,
                'linkVersions' => $project->whatsappLinkVersions()->latest('version_no')->get(),
                'deliveries' => $project->inviteDeliveries()->with('user:id,full_name')->latest('id')->limit(100)->get(),
                'canUpdate' => $actor->can('update', $project),
                'canComplete' => $actor->can('complete', $project),
                'canCancel' => $actor->can('cancel', $project),
                'canHold' => $actor->can('hold', $project),
                'canResume' => $actor->can('resume', $project),
            ]);
        }

        return response()->json(['data' => $project]);
    }

    public function store(StoreProjectRequest $request): JsonResponse|RedirectResponse
    {
        $project = $this->service->create(
            $request->only(['name', 'description']),
            Client::findOrFail($request->integer('client_id')),
            $request->input('department_ids'),
            $request->input('links', []),
            $request->user(),
        );

        if (! $request->expectsJson()) {
            return redirect()->route('projects.show', $project)->with('status', __('agencyos.projects.flash.created'));
        }

        return response()->json(['data' => $project], 201);
    }

    public function update(UpdateProjectRequest $request, Project $project): JsonResponse|RedirectResponse
    {
        $project->update($request->only(['name', 'description']));

        if (! $request->expectsJson()) {
            return redirect()->route('projects.show', $project)->with('status', __('agencyos.projects.flash.updated'));
        }

        return response()->json(['data' => $project->refresh()]);
    }

    public function complete(Request $request, Project $project): JsonResponse|RedirectResponse
    {
        $updated = $this->service->complete($project, $request->user());

        return $this->respond($request, $updated, __('agencyos.projects.flash.completed'));
    }

    public function cancel(CancelProjectRequest $request, Project $project): JsonResponse|RedirectResponse
    {
        $updated = $this->service->cancel($project, $request->string('reason')->toString(), $request->user());

        return $this->respond($request, $updated, __('agencyos.projects.flash.cancelled'));
    }

    public function hold(HoldProjectRequest $request, Project $project): JsonResponse|RedirectResponse
    {
        $updated = $this->service->hold($project, $request->string('reason')->toString(), $request->user());

        return $this->respond($request, $updated, __('agencyos.projects.flash.held'));
    }

    public function resume(Request $request, Project $project): JsonResponse|RedirectResponse
    {
        $updated = $this->service->resume($project, $request->user());

        return $this->respond($request, $updated, __('agencyos.projects.flash.resumed'));
    }

    private function respond(Request $request, Project $project, string $flash): JsonResponse|RedirectResponse
    {
        if (! $request->expectsJson()) {
            return redirect()->route('projects.show', $project)->with('status', $flash);
        }

        return response()->json(['data' => $project]);
    }
}
