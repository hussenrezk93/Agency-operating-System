<?php

namespace App\Http\Requests;

use App\Enums\DepartmentSpecialRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Second authorization layer: refuses before the department is ever updated. */
class UpdateDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('department')) ?? false;
    }

    public function rules(): array
    {
        $department = $this->route('department');

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('departments', 'name')->ignore($department?->id)],
            'special_role' => ['nullable', Rule::enum(DepartmentSpecialRole::class)],
        ];
    }
}
