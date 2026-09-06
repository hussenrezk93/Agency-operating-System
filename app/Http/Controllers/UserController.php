<?php

namespace App\Http\Controllers;

use App\Enums\RoleCode;
use App\Http\Requests\ResetUserPasswordRequest;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Department;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Thin controller — validation lives in the Form Requests, business rules in
 * UserService, authorization in UserPolicy (enforced twice: route middleware,
 * FormRequest::authorize() / explicit authorize() call here).
 */
class UserController extends Controller
{
    public function __construct(private readonly UserService $service) {}

    public function index(Request $request): JsonResponse|View
    {
        $this->authorize('viewAny', User::class);
        $actor = $request->user();

        // Grouped by department (each department's users listed together) rather than
        // a flat alphabetical list, for every viewing role. Within a department, its
        // Team Leader sorts first and everyone else follows by name. Admin/Manager
        // accounts carry no department (BRD §6) and sort after every departmental group.
        $query = User::query()
            ->select('users.*')
            ->leftJoin('departments', 'users.department_id', '=', 'departments.id')
            ->leftJoin('roles', 'users.role_id', '=', 'roles.id')
            ->with(['role:id,code', 'department:id,name'])
            ->orderByRaw('departments.name IS NULL')
            ->orderBy('departments.name')
            ->orderByRaw("roles.code = 'tl' desc")
            ->orderBy('users.full_name');

        // An Admin sees every account including other Admins (visible since 2026-08, once
        // Admin-creates-Admin shipped). A Manager sees every account down to their own
        // range including other Managers (2026-09, the same widening) — either way
        // UserPolicy::manage() still refuses to manage the actor's own row, so the Blade
        // view hides Edit/Disable/Reset-password there regardless of role.
        if ($actor->hasRole(RoleCode::Admin)) {
            $query->whereHas('role', fn ($q) => $q->whereIn('code', [
                RoleCode::Admin->value, RoleCode::Manager->value, RoleCode::TeamLeader->value, RoleCode::Employee->value,
            ]));
        } else {
            $query->whereHas('role', fn ($q) => $q->whereIn('code', [
                RoleCode::Manager->value, RoleCode::TeamLeader->value, RoleCode::Employee->value,
            ]));
        }

        $users = $query->paginate(50)->withQueryString();

        if (! $request->expectsJson()) {
            return view('users.index', [
                'users' => $users,
                'canCreate' => $actor->can('create', User::class),
            ]);
        }

        return response()->json(['data' => $users]);
    }

    /** Blade-only — the create-user form. */
    public function create(Request $request): View
    {
        $actor = $request->user();
        $this->authorize('viewAny', User::class);

        return view('users.create', [
            'departments' => Department::availableForStaffing()->orderBy('name')->get(),
            'isAdmin' => $actor->hasRole(RoleCode::Admin),
        ]);
    }

    /** Blade-only — the edit-user form. */
    public function edit(Request $request, User $user): View
    {
        $this->authorize('manage', $user);

        return view('users.edit', [
            'user' => $user,
            'departments' => Department::availableForStaffing()->orderBy('name')->get(),
        ]);
    }

    public function store(StoreUserRequest $request): JsonResponse|RedirectResponse
    {
        $role = RoleCode::from($request->string('role')->toString());
        $department = $request->integer('department_id')
            ? Department::findOrFail($request->integer('department_id'))
            : null;

        $result = $this->service->create(
            $role,
            $request->only(['full_name', 'username', 'personal_email']),
            $department,
            $request->user(),
        );

        if (! $request->expectsJson()) {
            return redirect()->route('users.index')
                ->with('status', __('agencyos.users.flash.created', ['username' => $result['user']->username]))
                ->with('temporary_password', $result['temporary_password']);
        }

        return response()->json([
            'data' => $result['user'],
            'temporary_password' => $result['temporary_password'],
        ], 201);
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse|RedirectResponse
    {
        $updated = $this->service->updateProfile(
            $user,
            $request->only(['full_name', 'personal_email', 'department_id']),
            $request->user(),
        );

        if (! $request->expectsJson()) {
            return redirect()->route('users.index')->with('status', __('agencyos.users.flash.updated'));
        }

        return response()->json(['data' => $updated]);
    }

    public function disable(Request $request, User $user): JsonResponse|RedirectResponse
    {
        $this->authorize('manage', $user);

        $updated = $this->service->disable($user, $request->user());

        if (! $request->expectsJson()) {
            return redirect()->route('users.index')->with('status', __('agencyos.users.flash.disabled'));
        }

        return response()->json(['data' => $updated]);
    }

    public function reactivate(Request $request, User $user): JsonResponse|RedirectResponse
    {
        $this->authorize('manage', $user);

        $updated = $this->service->reactivate($user, $request->user());

        if (! $request->expectsJson()) {
            return redirect()->route('users.index')->with('status', __('agencyos.users.flash.reactivated'));
        }

        return response()->json(['data' => $updated]);
    }

    public function resetPassword(ResetUserPasswordRequest $request, User $user): JsonResponse|RedirectResponse
    {
        $temporaryPassword = $this->service->resetPassword($user, $request->user());

        if (! $request->expectsJson()) {
            return redirect()->route('users.index')
                ->with('status', __('agencyos.users.flash.password_reset', ['username' => $user->username]))
                ->with('temporary_password', $temporaryPassword);
        }

        return response()->json(['temporary_password' => $temporaryPassword]);
    }
}
