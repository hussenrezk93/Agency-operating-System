<?php

namespace App\Http\Requests;

use App\Enums\AdjustmentType;
use App\Models\PerformanceAdjustment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePerformanceAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', PerformanceAdjustment::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'month' => ['required', 'date_format:Y-m'],
            'type' => ['required', Rule::enum(AdjustmentType::class)],
            // The DB's own pa_amount_check refuses anything <= 0; this is the same rule
            // stated where the person can actually be told about it.
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
            // Set by the payroll screen, which offers the same form so the Manager can
            // set the money and see the take-home change without leaving the page.
            'return' => ['nullable', 'in:payroll'],
        ];
    }
}
