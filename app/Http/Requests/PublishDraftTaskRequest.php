<?php

namespace App\Http\Requests;

use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** BRD §8 — publishing a draft is the moment the first department is chosen. */
class PublishDraftTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        $task = $this->route('task');

        return $task instanceof Task && ($this->user()?->can('publish', $task) ?? false);
    }

    public function rules(): array
    {
        return [
            'first_department_id' => [
                'required',
                'integer',
                Rule::exists('departments', 'id')->where('is_active', true),
            ],
        ];
    }
}
