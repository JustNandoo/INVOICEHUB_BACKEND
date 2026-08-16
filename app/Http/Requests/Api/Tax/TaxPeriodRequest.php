<?php

namespace App\Http\Requests\Api\Tax;

use Illuminate\Foundation\Http\FormRequest;

class TaxPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'month' => $this->input('month', now()->month),
            'year' => $this->input('year', now()->year),
        ]);
    }

    public function rules(): array
    {
        return [
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,2100'],
        ];
    }
}
