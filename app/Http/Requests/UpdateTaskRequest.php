<?php

namespace App\Http\Requests;

use App\Enums\Priority;
use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** BRD §8 — the creator edits a task's own data only until the first step is assigned. */
class UpdateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        $task = $this->route('task');

        return $task instanceof Task && ($this->user()?->can('update', $task) ?? false);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'brief' => ['required', 'string'],
            'notes' => ['nullable', 'string'],
            'priority' => ['sometimes', Rule::enum(Priority::class)],
        ];
    }
}
