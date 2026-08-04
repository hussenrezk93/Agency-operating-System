<?php

namespace App\Http\Requests;

use App\Models\DepartmentRoute;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Second authorization layer, and the clean-422 home for "no self routing" — the
 * database CHECK `dr_no_self_route` is a backstop, not the primary UX.
 */
class UpsertDepartmentRouteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage', DepartmentRoute::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'from_department_id' => ['required', 'integer', 'exists:departments,id', 'different:to_department_id'],
            'to_department_id' => ['required', 'integer', 'exists:departments,id'],
            'is_allowed' => ['required', 'boolean'],
        ];
    }
}
