<?php

namespace App\Http\Requests;

use App\Models\TaskStep;
use Illuminate\Foundation\Http\FormRequest;

class AddTaskStepCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $step = $this->route('step');

        return $step instanceof TaskStep
            && ($this->user()?->can('addComment', $step) ?? false);
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:5000'],
        ];
    }
}
