<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * CR-002 — the self-service counterpart to ForcedPasswordRequest. No `current_password`
 * rule: the whole point of this flow is that the user doesn't know their old one. No
 * authenticated-user check either — this route is deliberately unauthenticated, proven
 * by the token, not the session.
 */
class PasswordResetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'password' => ['required', 'confirmed', Password::min(8)],
        ];
    }
}
