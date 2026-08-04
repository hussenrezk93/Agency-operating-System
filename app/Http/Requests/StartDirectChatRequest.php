<?php

namespace App\Http\Requests;

use App\Models\ChatConversation;
use Illuminate\Foundation\Http\FormRequest;

class StartDirectChatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('startDirect', ChatConversation::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ];
    }
}
