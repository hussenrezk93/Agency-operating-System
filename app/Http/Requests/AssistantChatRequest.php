<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssistantChatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:2000'],
            'history' => ['array', 'max:20'],
            'history.*.role' => ['required_with:history', 'in:user,model'],
            'history.*.text' => ['required_with:history', 'string', 'max:2000'],
        ];
    }
}
