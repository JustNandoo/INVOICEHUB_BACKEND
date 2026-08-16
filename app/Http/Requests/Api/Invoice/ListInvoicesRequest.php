<?php

namespace App\Http\Requests\Api\Invoice;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListInvoicesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'perPage' => $this->input('perPage', 15),
            'page' => $this->input('page', 1),
        ]);
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:150'],
            'status' => ['nullable', Rule::in(['draft', 'unpaid', 'overdue', 'paid', 'void'])],
            'issueFrom' => ['nullable', 'date_format:Y-m-d'],
            'issueTo' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:issueFrom'],
            'dueFrom' => ['nullable', 'date_format:Y-m-d'],
            'dueTo' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:dueFrom'],
            'sort' => ['nullable', Rule::in(['newest', 'oldest', 'dueSoon', 'amountHigh', 'amountLow'])],
            'perPage' => ['required', 'integer', 'min:1', 'max:100'],
            'page' => ['required', 'integer', 'min:1'],
        ];
    }
}
