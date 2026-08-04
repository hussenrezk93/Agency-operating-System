<?php

namespace App\Http\Requests;

use App\Models\DepartmentLeadershipAssignment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Second authorization layer: the request refuses before the service is ever called. */
class AppointTemporaryLeaderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('appoint', DepartmentLeadershipAssignment::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'department_id' => ['required', 'integer', Rule::exists('departments', 'id')->where('is_active', true)],
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            // Q9 — a temporary appointment exists only for a primary TL's leave.
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }
}
