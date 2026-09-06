<?php

namespace App\Http\Requests;

use App\Models\Expense;
use Illuminate\Foundation\Http\FormRequest;

class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Expense::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'spent_on' => ['required', 'date'],
            'description' => ['required', 'string', 'min:2', 'max:500'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999999.99'],
        ];
    }
}
