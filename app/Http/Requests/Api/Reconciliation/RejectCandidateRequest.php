<?php

namespace App\Http\Requests\Api\Reconciliation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RejectCandidateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'invoiceId' => ['required', 'integer', Rule::exists('invoices', 'id')->where('user_id', $this->user()->id)->whereNull('deleted_at')],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
