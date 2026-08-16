<?php

namespace App\Http\Requests\Api\Reconciliation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListReconciliationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['perPage' => $this->input('perPage', 20), 'page' => $this->input('page', 1)]);
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::in(['confirmed', 'reversed'])],
            'matchedBy' => ['nullable', Rule::in(['manual', 'exact_rule'])],
            'dateFrom' => ['nullable', 'date_format:Y-m-d'],
            'dateTo' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:dateFrom'],
            'perPage' => ['required', 'integer', 'min:1', 'max:100'],
            'page' => ['required', 'integer', 'min:1'],
        ];
    }
}
