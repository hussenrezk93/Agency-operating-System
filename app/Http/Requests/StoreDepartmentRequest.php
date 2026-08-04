<?php

namespace App\Http\Requests;

use App\Models\Department;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Second authorization layer: refuses before DepartmentService is ever called.
 * The "primary leader must be a Team Leader" business rule lives in
 * DepartmentService::createWithPrimaryLeader() — this request only validates shape.
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
            'primary_leader_id' => ['required', 'integer', 'exists:users,id'],
        ];
    }
}
