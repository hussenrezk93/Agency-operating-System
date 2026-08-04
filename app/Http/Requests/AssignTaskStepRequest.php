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

    /**
     * BRD §11 — the dept TL may edit a step's deadline with no reason required; only
     * actually replacing the employee needs one. A reassignment that keeps the same
     * assignee (a pure date edit through the same endpoint) is that case.
     */
    public function isEmployeeChanging(): bool
    {
        if (! $this->routeIs('tasks.steps.reassign')) {
            return false;
        }

        $step = $this->route('step');
        $currentAssigneeId = $step instanceof TaskStep ? $step->activeAssignment?->assignee_id : null;

        return $currentAssigneeId === null || (int) $this->input('assignee_id') !== $currentAssigneeId;
    }

    public function rules(): array
    {
        return [
            'assignee_id' => ['required', 'integer', 'exists:users,id'],
            'start_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => [$this->isEmployeeChanging() ? 'required' : 'nullable', 'string', 'max:1000'],
        ];
    }
}
