<?php

namespace App\Http\Requests;

use App\Models\TaskStep;
use Illuminate\Foundation\Http\FormRequest;

class AssignTaskStepRequest extends FormRequest
{
    public function authorize(): bool
    {
        $step = $this->route('step');

        return $step instanceof TaskStep
            && ($this->user()?->can('assign', $step) ?? false);
    }

    public function rules(): array
    {
        $isReassignment = $this->routeIs('tasks.steps.reassign');

        return [
            'assignee_id' => ['required', 'integer', 'exists:users,id'],
            'start_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => [$isReassignment ? 'required' : 'nullable', 'string', 'max:1000'],
        ];
    }
}
