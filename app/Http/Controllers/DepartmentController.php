<?php

namespace App\Http\Controllers;

use App\Enums\RoleCode;
use App\Http\Requests\StoreDepartmentRequest;
use App\Http\Requests\UpdateDepartmentRequest;
use App\Models\Department;
use App\Models\User;
use App\Services\DepartmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Thin controller — the primary-leader business rule lives in DepartmentService
 * (already built and covered by OrganizationStructureTest); this controller only
 * exposes it, plus rename/deactivate/reactivate, behind DepartmentPolicy.
 */
class DepartmentController extends Controller
{
    public function __construct(private readonly DepartmentService $service) {}

    public function index(Request $request): JsonResponse|View
    {
        $this->authorize('viewAny', Department::class);

        // The Blade list shows each department's primary leader — eager-loading
        // leadershipAssignments.user lets Department::primaryLeader() resolve it
        // in-memory instead of firing one query per row.
        $departments = Department::query()->orderBy('name')->with('leadershipAssignments.user')->get();

        if (! $request->expectsJson()) {
            return view('departments.index', [
                'departments' => $departments,
                'canCreate' => $request->user()->can('create', Department::class),
            ]);
        }

        return response()->json(['data' => $departments]);
    }

    /** Blade-only — the create-department form. */
    public function create(Request $request): View
    {
        $this->authorize('create', Department::class);

        return view('departments.create', [
            // Only TLs not already leading an active department — the DB's
            // "one active led department per user" constraint would otherwise reject
            // the choice with a raw query error instead of a clean validation message.
            'leaders' => User::whereHas('role', fn ($q) => $q->where('code', RoleCode::TeamLeader->value))
                ->whereDoesntHave('primaryLeadershipAssignments', fn ($q) => $q->currentlyActive())
                ->orderBy('full_name')->get(),
        ]);
    }

    /** Blade-only — the rename form. */
    public function edit(Request $request, Department $department): View
    {
        $this->authorize('update', $department);

        return view('departments.edit', ['department' => $department]);
    }

    public function store(StoreDepartmentRequest $request): JsonResponse|RedirectResponse
    {
        $department = $this->service->createWithPrimaryLeader(
            $request->string('name')->toString(),
            User::findOrFail($request->integer('primary_leader_id')),
            $request->user(),
        );

        if (! $request->expectsJson()) {
            return redirect()->route('departments.index')->with('status', __('agencyos.departments.flash.created'));
        }

        return response()->json(['data' => $department], 201);
    }

    public function update(UpdateDepartmentRequest $request, Department $department): JsonResponse|RedirectResponse
    {
        $department = $this->service->rename($department, $request->string('name')->toString(), $request->user());

        if (! $request->expectsJson()) {
            return redirect()->route('departments.index')->with('status', __('agencyos.departments.flash.updated'));
        }

        return response()->json(['data' => $department]);
    }

    public function deactivate(Request $request, Department $department): JsonResponse|RedirectResponse
    {
        $this->authorize('deactivate', $department);

        $department = $this->service->deactivate($department, $request->user());

        if (! $request->expectsJson()) {
            return redirect()->route('departments.index')->with('status', __('agencyos.departments.flash.deactivated'));
        }

        return response()->json(['data' => $department]);
    }

    public function reactivate(Request $request, Department $department): JsonResponse|RedirectResponse
    {
        $this->authorize('reactivate', $department);

        $department = $this->service->reactivate($department, $request->user());

        if (! $request->expectsJson()) {
            return redirect()->route('departments.index')->with('status', __('agencyos.departments.flash.reactivated'));
        }

        return response()->json(['data' => $department]);
    }
}
