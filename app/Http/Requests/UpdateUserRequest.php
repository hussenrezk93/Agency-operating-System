<?php

namespace App\Http\Requests;

use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Second authorization layer: refuses before UserService is ever called. */
class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage', $this->route('user')) ?? false;
    }

    public function rules(): array
    {
        $subject = $this->route('user');

        return [
            'full_name' => ['sometimes', 'string', 'max:255'],
            'personal_email' => [
                'sometimes', 'email', 'max:255',
                Rule::unique('users', 'personal_email')->ignore($subject?->id),
            ],
            // Mirrors Department::scopeAvailableForStaffing() — see StoreUserRequest.
            'department_id' => [
                'sometimes', 'nullable', 'integer',
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
