<?php

namespace App\Http\Requests;

use App\Models\Client;
use Illuminate\Foundation\Http\FormRequest;

/** Second authorization layer: refuses before the client is ever updated. */
class UpdateClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', Client::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'short_description' => ['sometimes', 'nullable', 'string'],
            'phone' => ['sometimes', 'string', 'max:50', 'regex:/^(?=(?:.*[0-9]){7,})[0-9\s()+\-]+$/'],
            'company_email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'website_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
        ];
    }
}
