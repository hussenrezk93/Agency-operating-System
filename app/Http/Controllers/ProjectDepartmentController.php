<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddProjectDepartmentRequest;
use App\Models\Department;
use App\Models\Project;
use App\Services\ProjectService;
use App\Services\ProjectWhatsappService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProjectDepartmentController extends Controller
{
    public function __construct(
        private readonly ProjectService $service,
        private readonly ProjectWhatsappService $whatsapp,
    ) {}

    public function store(AddProjectDepartmentRequest $request, Project $project): JsonResponse|RedirectResponse
    {
        $department = Department::findOrFail($request->integer('department_id'));

        $updated = $this->service->addDepartment($project, $department, $request->user());

        // BRD §7.3 — a newly added department invites only its own members, once.
        $this->whatsapp->fanOutForDepartment($updated, $department);

        if (! $request->expectsJson()) {
            return redirect()->route('projects.show', $updated)->with('status', __('agencyos.projects.flash.department_added'));
        }

        return response()->json(['data' => $updated], 201);
    }

    public function destroy(Request $request, Project $project, Department $department): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $project);

        $updated = $this->service->removeDepartment($project, $department, $request->user());

        if (! $request->expectsJson()) {
            return redirect()->route('projects.show', $updated)->with('status', __('agencyos.projects.flash.department_removed'));
        }

        return response()->json(['data' => $updated]);
    }
}
