<?php

namespace App\Http\Requests\Api\Reconciliation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ImportBankTransactionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bankAccountId' => ['required', 'integer', Rule::exists('bank_accounts', 'id')->where('user_id', $this->user()->id)->whereNull('deleted_at')],
            'file' => ['required', 'file', 'max:10240', 'mimes:csv,txt,xlsx,xls'],
        ];
    }
}
