<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpsertDepartmentOutputAccessRequest;
use App\Models\Department;
use App\Models\DepartmentOutputAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Admin-only matrix editor over `department_output_access` (BRD §15). */
class DepartmentOutputAccessController extends Controller
{
    public function index(Request $request): JsonResponse|View
    {
        $this->authorize('viewAny', DepartmentOutputAccess::class);

        $rules = DepartmentOutputAccess::query()
            ->with(['viewerDepartment:id,name', 'sourceDepartment:id,name'])
            ->get();

        if (! $request->expectsJson()) {
            return view('department-output-access.index', [
                'rules' => $rules,
                'departments' => Department::where('is_active', true)->orderBy('name')->get(),
            ]);
        }

        return response()->json(['data' => $rules]);
    }

    public function upsert(UpsertDepartmentOutputAccessRequest $request): JsonResponse|RedirectResponse
    {
        $rule = DepartmentOutputAccess::updateOrCreate(
            [
                'viewer_department_id' => $request->integer('viewer_department_id'),
                'source_department_id' => $request->integer('source_department_id'),
            ],
            [
                'scope' => $request->string('scope')->toString(),
                'is_allowed' => $request->boolean('is_allowed'),
                'updated_by' => $request->user()->id,
                'updated_at' => now(),
            ],
        );

        if (! $request->expectsJson()) {
            return redirect()->route('department-output-access.index')->with('status', __('agencyos.output_access.flash.updated'));
        }

        return response()->json(['data' => $rule]);
    }
}
