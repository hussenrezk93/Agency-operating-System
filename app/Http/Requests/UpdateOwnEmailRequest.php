<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** BRD §18.1 — a user editing their own personal email from their profile. */
class UpdateOwnEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'personal_email' => [
                'required', 'email', 'max:255',
                Rule::unique('users', 'personal_email')->ignore($this->user()?->id),
            ],
        ];
    }
}
