<?php

namespace App\Http\Requests;

use App\Enums\OutputAccessScope;
use App\Models\DepartmentOutputAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Second authorization layer, and the clean-422 home for "no self access" — the
 * database CHECK `doa_no_self` is a backstop, not the primary UX.
 */
class UpsertDepartmentOutputAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage', DepartmentOutputAccess::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'viewer_department_id' => ['required', 'integer', 'exists:departments,id', 'different:source_department_id'],
            'source_department_id' => ['required', 'integer', 'exists:departments,id'],
            'scope' => ['required', Rule::enum(OutputAccessScope::class)],
            'is_allowed' => ['required', 'boolean'],
        ];
    }
}
