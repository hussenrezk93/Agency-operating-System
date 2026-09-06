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
            'url' => ['required_without:media', 'prohibits:media', 'nullable', 'url', 'max:2048'],
            'media' => ['required_without:url', 'nullable', 'file', 'mimes:jpg,jpeg,png,webp,mp4,mov,webm,m4v', 'max:51200'],
            'label' => ['nullable', 'string', 'max:255'],
        ];
    }
}
