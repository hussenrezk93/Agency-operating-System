<?php

namespace App\Http\Requests;

use App\Models\Department;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Authorization mirrors department update (Admin/Manager) — the "must be a Team
 * Leader" and "department already has one" rules live in
 * DepartmentService::assignPrimaryLeader(); this request only validates shape.
 */
class AssignDepartmentLeaderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('department')) ?? false;
    }

    public function rules(): array
    {
        return [
            'primary_leader_id' => ['required', 'integer', 'exists:users,id'],
        ];
    }
}
