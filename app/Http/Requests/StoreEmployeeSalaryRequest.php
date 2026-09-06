<?php

namespace App\Http\Requests;

use App\Models\EmployeeSalary;
use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeSalaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', EmployeeSalary::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            // A salary of 0 is allowed (unpaid intern, a month off contract); a negative
            // one is refused here and again by es_amount_check in the database.
            'monthly_amount' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'note' => ['nullable', 'string', 'max:500'],
            'month' => ['nullable', 'date_format:Y-m'],
        ];
    }
}
