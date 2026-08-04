<?php

namespace App\Http\Requests;

use App\Enums\ReviewDecision;
use App\Models\TaskStep;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewTaskStepRequest extends FormRequest
{
    public function authorize(): bool
    {
        $step = $this->route('step');

        return $step instanceof TaskStep
            && ($this->user()?->can('review', $step) ?? false);
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::enum(ReviewDecision::class)],
            'comment' => [
                Rule::requiredIf($this->input('decision') === ReviewDecision::ChangesRequested->value),
                'nullable',
                'string',
                'max:5000',
            ],
        ];
    }
}
