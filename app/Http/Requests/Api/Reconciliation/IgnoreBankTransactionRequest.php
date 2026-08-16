<?php

namespace App\Http\Requests\Api\Reconciliation;

use Illuminate\Foundation\Http\FormRequest;

class IgnoreBankTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['reason' => ['nullable', 'string', 'max:500']];
    }
}
