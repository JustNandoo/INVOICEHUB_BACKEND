<?php

namespace App\Http\Requests\Api\Tax;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResolveTaxFindingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'resolution' => ['required', Rule::in(['not_taxable', 'duplicate', 'corrected', 'other'])],
            'notes' => ['required', 'string', 'max:1000'],
        ];
    }
}
