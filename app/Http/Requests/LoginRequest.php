<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class LoginRequest extends FormRequest
{
    /** Throttle key: username + IP, so one attacker cannot lock out every account. */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower((string) $this->input('username')).'|'.$this->ip());
    }

    public function authorize(): bool
    {
        return true; // guests only — enforced by the 'guest' middleware on the route
    }

    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ];
    }
}
