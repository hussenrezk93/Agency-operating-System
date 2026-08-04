<?php

namespace App\Http\Controllers;

use App\Enums\RoleCode;
use App\Http\Requests\AppointTemporaryLeaderRequest;
use App\Http\Requests\ReplaceTemporaryLeaderRequest;
use App\Models\Department;
use App\Models\DepartmentLeadershipAssignment;
use App\Models\User;
use App\Services\TemporaryLeadershipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Thin controller — validation lives in the Form Requests, business rules in
 * TemporaryLeadershipService, authorization in TemporaryLeadershipPolicy
 * (enforced three times over: route middleware, request authorize(), explicit
 * authorize() call here).
 */
class TemporaryLeadershipController extends Controller
{
    public function __construct(private readonly TemporaryLeadershipService $service) {}

    public function index(Request $request): JsonResponse|View
    {
        $this->authorize('viewAny', DepartmentLeadershipAssignment::class);

        $assignments = DepartmentLeadershipAssignment::query()
            ->where('assignment_type', 'temporary')
            ->with(['department:id,name', 'user:id,full_name'])
            ->latest('id')
            ->get();

        if (! $request->expectsJson()) {
            return view('temporary-leadership.index', ['assignments' => $assignments]);
        }

        return response()->json(['data' => $assignments]);
    }

    /** Blade-only — the appoint-temporary-leader form. */
    public function create(Request $request): View
    {
        $this->authorize('appoint', DepartmentLeadershipAssignment::class);

        return view('temporary-leadership.create', [
            'departments' => Department::where('is_active', true)->orderBy('name')->get(),
            'candidates' => User::whereHas('role', fn ($q) => $q->whereIn('code', [RoleCode::TeamLeader->value, RoleCode::Employee->value]))
                ->where('status', 'active')
                ->orderBy('full_name')
                ->get(),
        ]);
    }

    public function store(AppointTemporaryLeaderRequest $request): JsonResponse|RedirectResponse
    {
        $this->authorize('appoint', DepartmentLeadershipAssignment::class);

        $assignment = $this->service->appoint(
            Department::findOrFail($request->integer('department_id')),
            User::findOrFail($request->integer('user_id')),
            Carbon::parse($request->date('start_date')),
            Carbon::parse($request->date('end_date')),
            $request->string('reason')->toString(),
            $request->user(),
        );

        if (! $request->expectsJson()) {
            return redirect()->route('temporary-leadership.index')->with('status', __('agencyos.temporary_leadership.flash.appointed'));
        }

        return response()->json(['data' => $assignment], 201);
    }

    public function replace(
        ReplaceTemporaryLeaderRequest $request,
        DepartmentLeadershipAssignment $assignment,
    ): JsonResponse {
        $this->authorize('replace', $assignment);

        $new = $this->service->replace(
            $assignment,
            User::findOrFail($request->integer('user_id')),
            Carbon::parse($request->date('end_date')),
            $request->string('reason')->toString(),
            $request->user(),
        );

        return response()->json(['data' => $new]);
    }

    public function endEarly(Request $request, DepartmentLeadershipAssignment $assignment): JsonResponse|RedirectResponse
    {
        $this->authorize('endEarly', $assignment);

        $this->service->endEarly($assignment, $request->user());

        if (! $request->expectsJson()) {
            return redirect()->route('temporary-leadership.index')->with('status', __('agencyos.temporary_leadership.flash.ended'));
        }

        return response()->json(['data' => $assignment->refresh()]);
    }
}
