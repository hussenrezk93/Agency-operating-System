<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpsertDepartmentRouteRequest;
use App\Models\Department;
use App\Models\DepartmentRoute;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin-only matrix editor over `department_routes` (BRD §15). The routing DECISION
 * at "Send to Next Department" is TaskRoutingService's job — this only edits the
 * matrix it reads from.
 */
class DepartmentRoutingController extends Controller
{
    public function index(Request $request): JsonResponse|View
    {
        $this->authorize('viewAny', DepartmentRoute::class);

        $routes = DepartmentRoute::query()
            ->with(['fromDepartment:id,name', 'toDepartment:id,name'])
            ->get();

        if (! $request->expectsJson()) {
            $allowed = [];
            foreach ($routes as $route) {
                $allowed[$route->from_department_id][$route->to_department_id] = $route->is_allowed;
            }

            return view('department-routes.index', [
                'departments' => Department::where('is_active', true)->orderBy('name')->get(),
                'allowed' => $allowed,
            ]);
        }

        return response()->json(['data' => $routes]);
    }

    public function upsert(UpsertDepartmentRouteRequest $request): JsonResponse|RedirectResponse
    {
        $route = DepartmentRoute::updateOrCreate(
            [
                'from_department_id' => $request->integer('from_department_id'),
                'to_department_id' => $request->integer('to_department_id'),
            ],
            [
                'is_allowed' => $request->boolean('is_allowed'),
                'updated_by' => $request->user()->id,
                'updated_at' => now(),
            ],
        );

        if (! $request->expectsJson()) {
            return redirect()->route('department-routes.index')->with('status', __('agencyos.routing.flash.updated'));
        }

        return response()->json(['data' => $route]);
    }
}
