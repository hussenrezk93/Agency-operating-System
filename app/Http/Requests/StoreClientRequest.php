<?php

namespace App\Http\Requests;

use App\Models\Client;
use Illuminate\Foundation\Http\FormRequest;

/** Second authorization layer: refuses before ClientService is ever called. */
class StoreClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Client::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'short_description' => ['nullable', 'string'],
            'phone' => ['required', 'string', 'max:50'],
            'company_email' => ['nullable', 'email', 'max:255'],
            'website_url' => ['nullable', 'url', 'max:2048'],
        ];
    }
}
