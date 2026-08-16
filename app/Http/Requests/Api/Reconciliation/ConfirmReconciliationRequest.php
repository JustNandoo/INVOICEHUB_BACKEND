<?php

namespace App\Http\Requests\Api\Reconciliation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfirmReconciliationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bankTransactionId' => ['required', 'integer', Rule::exists('bank_transactions', 'id')->where('user_id', $this->user()->id)],
            'invoiceId' => ['required', 'integer', Rule::exists('invoices', 'id')->where('user_id', $this->user()->id)->whereNull('deleted_at')],
            'appliedAmount' => ['required', 'integer', 'min:1'],
            'score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'adjustments' => ['sometimes', 'array', 'max:10'],
            'adjustments.*.type' => ['required', Rule::in(['bank_fee', 'marketplace_fee', 'rounding', 'other'])],
            'adjustments.*.amount' => ['required', 'integer', 'min:1'],
            'adjustments.*.description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
