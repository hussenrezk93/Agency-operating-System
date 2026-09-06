<?php

namespace App\Http\Requests;

use App\Enums\RoleCode;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Second authorization layer: refuses before UserService is ever called. */
class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $role = RoleCode::tryFrom((string) $this->input('role'));

        return $role !== null && ($this->user()?->can('createWithRole', [User::class, $role]) ?? false);
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:users,username'],
            'personal_email' => ['required', 'email', 'max:255', 'unique:users,personal_email'],
            'role' => ['required', Rule::enum(RoleCode::class)],
            // BRD §6 — a Team Leader/Employee belongs to exactly one department; Admin/Manager belong to none.
            // Active departments, plus leaderless ones awaiting a primary leader
            // (Department::scopeAvailableForStaffing()) — mirrored here since this rule
            // runs against the raw `departments` table, not the Eloquent model.
            'department_id' => [
                'nullable',
                'integer',
                'required_if:role,tl,employee',
                'prohibited_if:role,admin,manager',
                Rule::exists('departments', 'id')->where(function (Builder $query): void {
                    $query->where('is_active', true)->orWhereNotExists(function (Builder $sub): void {
                        $sub->selectRaw('1')
                            ->from('department_leadership_assignments')
                            ->whereColumn('department_leadership_assignments.department_id', 'departments.id');
                    });
                }),
            ],
        ];
    }
}
