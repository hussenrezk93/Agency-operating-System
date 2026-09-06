<?php

namespace App\Http\Requests;

use App\Enums\DepartmentSpecialRole;
use App\Models\Department;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Second authorization layer: refuses before DepartmentService is ever called.
 * The primary leader is optional at creation time — a department with none is
 * created inactive (DepartmentService::create()) until one is assigned later via
 * DepartmentService::assignPrimaryLeader(). The "must be a Team Leader" rule lives
 * in the service too — this request only validates shape.
 */
class StoreDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Department::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:departments,name'],
            'primary_leader_id' => ['nullable', 'integer', 'exists:users,id'],
            'special_role' => ['nullable', Rule::enum(DepartmentSpecialRole::class)],
        ];
    }
}
