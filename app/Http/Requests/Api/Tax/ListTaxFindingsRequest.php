<?php

namespace App\Http\Requests\Api\Tax;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListTaxFindingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['year' => $this->input('year', now()->year), 'month' => $this->input('month', now()->month)]);
    }

    public function rules(): array
    {
        return [
            'year' => ['required', 'integer', 'between:2000,2100'],
            'month' => ['required', 'integer', 'between:1,12'],
            'status' => ['sometimes', Rule::in(['open', 'resolved', 'dismissed'])],
        ];
    }
}
