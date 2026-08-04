<?php

namespace App\Http\Requests;

use App\Models\TaskStep;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransferTaskStepRequest extends FormRequest
{
    public function authorize(): bool
    {
        $step = $this->route('step');

        return $step instanceof TaskStep
            && ($this->user()?->can('transfer', $step) ?? false);
    }

    public function rules(): array
    {
        return [
            'to_department_id' => [
                'required',
                'integer',
                Rule::exists('departments', 'id')->where('is_active', true),
            ],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
