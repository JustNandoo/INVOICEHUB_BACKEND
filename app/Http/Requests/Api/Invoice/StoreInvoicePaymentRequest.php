<?php

namespace App\Http\Requests\Api\Invoice;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvoicePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('paidAt')) {
            $this->merge(['paidAt' => now()->toIso8601String()]);
        }
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'integer', 'min:1'],
            'method' => ['required', Rule::in(['bank_transfer', 'cash', 'e_wallet', 'marketplace', 'other'])],
            'reference' => ['nullable', 'string', 'max:100'],
            'paidAt' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
