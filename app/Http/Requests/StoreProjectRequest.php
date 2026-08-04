<?php

namespace App\Http\Requests;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Second authorization layer: refuses before ProjectService is ever called. The
 * TL department-scoping rule (BRD §7.2) needs the actor, so it lives in the service,
 * not here.
 */
class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Project::class) ?? false;
    }

    /** The Blade create form always posts a few static link rows; blank ones are not links. */
    protected function prepareForValidation(): void
    {
        if (! is_array($this->input('links'))) {
            return;
        }

        $this->merge([
            'links' => array_values(array_filter(
                $this->input('links'),
                fn ($link) => is_array($link) && trim((string) ($link['url'] ?? '')) !== '',
            )),
        ]);
    }

    /** BRD §7.2 — a new client may be created inline instead of picking an existing one. */
    public function isNewClient(): bool
    {
        return $this->input('client_source') === 'new';
    }

    public function rules(): array
    {
        $isNewClient = $this->isNewClient();

        return [
            'client_id' => [$isNewClient ? 'nullable' : 'required', 'integer', 'exists:clients,id'],
            'new_client_name' => [$isNewClient ? 'required' : 'nullable', 'string', 'max:255'],
            'new_client_phone' => [$isNewClient ? 'required' : 'nullable', 'string', 'max:50'],
            'new_client_email' => ['nullable', 'email', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'department_ids' => ['required', 'array', 'min:1'],
            'department_ids.*' => ['integer', 'exists:departments,id'],
            'links' => ['sometimes', 'array'],
            'links.*.url' => ['required_with:links', 'url', 'max:2048'],
            'links.*.label' => ['nullable', 'string', 'max:255'],
        ];
    }
}
