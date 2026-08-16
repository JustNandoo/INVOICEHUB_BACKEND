<?php

namespace App\Http\Requests\Api\Tax;

use Illuminate\Foundation\Http\FormRequest;

class MarkTaxReportReportedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'referenceNumber' => ['required', 'string', 'max:100'],
            'reportedAt' => ['sometimes', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
