<?php

namespace App\Http\Requests\Api\Customer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListCustomerInvoicesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['page' => $this->input('page', 1), 'perPage' => $this->input('perPage', 10)]);
    }

    public function rules(): array
    {
        return [
            'status' => ['sometimes', Rule::in(['draft', 'unpaid', 'paid', 'overdue', 'void'])],
            'page' => ['required', 'integer', 'min:1'],
            'perPage' => ['required', 'integer', 'between:1,100'],
        ];
    }
}
