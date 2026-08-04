<?php

namespace App\Http\Requests;

use App\Enums\Priority;
use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Task::class) ?? false;
    }

    /** The Blade create form always posts a few static link rows; blank ones are not links. */
    protected function prepareForValidation(): void
    {
        if (! is_array($this->input('reference_links'))) {
            return;
        }

        $this->merge([
            'reference_links' => array_values(array_filter(
                $this->input('reference_links'),
                fn ($link) => is_array($link) && trim((string) ($link['url'] ?? '')) !== '',
            )),
        ]);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'brief' => ['required', 'string'],
            'notes' => ['nullable', 'string'],
            'priority' => ['sometimes', Rule::enum(Priority::class)],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'first_department_id' => [
                'required',
                'integer',
                Rule::exists('departments', 'id')->where('is_active', true),
            ],
            'reference_links' => ['sometimes', 'array', 'max:20'],
            'reference_links.*.url' => ['required', 'url', 'max:2048'],
            'reference_links.*.label' => ['nullable', 'string', 'max:255'],
        ];
    }
}
