<?php

namespace App\Http\Requests\Api\Tax;

use Illuminate\Foundation\Http\FormRequest;

class MonthlyTaxReportsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['year' => $this->input('year', now()->year), 'months' => $this->input('months', 12)]);
    }

    public function rules(): array
    {
        return [
            'year' => ['required', 'integer', 'between:2000,2100'],
            'months' => ['required', 'integer', 'between:1,12'],
        ];
    }
}
