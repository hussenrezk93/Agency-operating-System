<?php

namespace App\Http\Requests;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;

/** URL host validation happens in ProjectWhatsappService, since the exact allow-list is a business rule. */
class SetWhatsappLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');

        return $project instanceof Project && ($this->user()?->can('update', $project) ?? false);
    }

    public function rules(): array
    {
        return [
            'url' => ['required', 'url', 'max:2048'],
            'label' => ['nullable', 'string', 'max:255'],
        ];
    }
}
