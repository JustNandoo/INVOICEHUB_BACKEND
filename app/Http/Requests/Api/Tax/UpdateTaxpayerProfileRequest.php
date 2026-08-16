<?php

namespace App\Http\Requests\Api\Tax;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaxpayerProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('npwp')) {
            $this->merge(['npwp' => preg_replace('/\D+/', '', (string) $this->input('npwp'))]);
        }
    }

    public function rules(): array
    {
        return [
            'taxpayerType' => ['required', Rule::in(['individual', 'entity'])],
            'taxpayerName' => ['required', 'string', 'max:150'],
            'businessName' => ['required', 'string', 'max:150'],
            'npwp' => ['nullable', 'digits_between:15,16'],
            'taxScheme' => ['sometimes', Rule::in(['final_umkm'])],
            'accountingMethod' => ['sometimes', Rule::in(['cash_basis'])],
            'effectiveFrom' => ['sometimes', 'date'],
        ];
    }
}
