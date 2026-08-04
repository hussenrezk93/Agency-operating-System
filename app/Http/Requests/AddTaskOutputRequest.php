<?php

namespace App\Http\Requests;

use App\Models\TaskStep;
use Illuminate\Foundation\Http\FormRequest;

class AddTaskOutputRequest extends FormRequest
{
    public function authorize(): bool
    {
        $step = $this->route('step');

        return $step instanceof TaskStep
            && ($this->user()?->can('addOutput', $step) ?? false);
    }

    public function rules(): array
    {
        return [
            'url' => ['required', 'url', 'max:2048'],
            'label' => ['nullable', 'string', 'max:255'],
        ];
    }
}
