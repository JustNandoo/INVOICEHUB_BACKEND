<?php

namespace App\Http\Requests\Api\Reconciliation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListBankTransactionsRequest extends FormRequest
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
            'search' => ['nullable', 'string', 'max:150'],
            'bankAccountId' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(['unmatched', 'needs_confirmation', 'matched', 'ignored'])],
            'type' => ['nullable', Rule::in(['credit', 'debit'])],
            'dateFrom' => ['nullable', 'date_format:Y-m-d'],
            'dateTo' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:dateFrom'],
            'perPage' => ['required', 'integer', 'min:1', 'max:100'],
            'page' => ['required', 'integer', 'min:1'],
        ];
    }
}
