<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Second authorization layer: refuses before UserService is ever called. */
class ResetUserPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage', $this->route('user')) ?? false;
    }

    public function rules(): array
    {
        return [];
    }
}
