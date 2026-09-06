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

    /**
     * The Blade create form always posts a few static link rows; a row is only real
     * once it carries a URL or an uploaded file. Original array keys are kept
     * (no array_values() reindex) — reindexing here would desync this merged `input()`
     * array from the untouched `$this->files` bag, which is still keyed by the
     * original slot index, breaking every `reference_links.N.media` file lookup
     * downstream (this request's own wildcard rules, and the controller's upload loop).
     */
    protected function prepareForValidation(): void
    {
        $links = $this->input('reference_links');

        if (! is_array($links)) {
            return;
        }

        $this->merge([
            'reference_links' => array_filter(
                $links,
                fn ($link, $key) => is_array($link)
                    && (trim((string) ($link['url'] ?? '')) !== '' || $this->hasFile("reference_links.$key.media")),
                ARRAY_FILTER_USE_BOTH,
            ),
        ]);
    }

    /** BRD §8 — saving as a draft defers picking the department (and the links) to publish time. */
    public function isDraft(): bool
    {
        return $this->input('intent') === 'draft';
    }

    public function rules(): array
    {
        $isDraft = $this->isDraft();

        return [
            'title' => ['required', 'string', 'max:255'],
            'brief' => ['required', 'string'],
            'notes' => ['nullable', 'string'],
            'priority' => ['sometimes', Rule::enum(Priority::class)],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'first_department_id' => [
                $isDraft ? 'nullable' : 'required',
                'integer',
                Rule::exists('departments', 'id')->where('is_active', true),
            ],
            'reference_links' => ['nullable', 'array', 'min:0', 'max:20'],
            'reference_links.*.url' => ['required_without:reference_links.*.media', 'prohibits:reference_links.*.media', 'nullable', 'url', 'max:2048'],
            'reference_links.*.media' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,mp4,mov,webm,m4v', 'max:51200'],
            'reference_links.*.label' => ['nullable', 'string', 'max:255'],
        ];
    }
}
