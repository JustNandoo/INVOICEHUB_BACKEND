<?php

namespace App\Http\Requests\Api\Invoice;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customerId' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('customers', 'id')->where('user_id', $this->user()->id)->whereNull('deleted_at'),
            ],
            'customer' => ['sometimes', 'array'],
            'customer.name' => ['required_with:customer', 'string', 'max:150'],
            'customer.email' => ['nullable', 'email:rfc', 'max:255'],
            'customer.whatsapp' => ['nullable', 'string', 'max:30'],
            'customer.address' => ['nullable', 'string', 'max:1000'],
            'issuerAddress' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'issueDate' => ['sometimes', 'date_format:Y-m-d'],
            'dueDate' => ['sometimes', 'date_format:Y-m-d'],
            'taxRate' => ['sometimes', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
            'discountAmount' => ['sometimes', 'integer', 'min:0'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'items' => ['sometimes', 'array', 'min:1', 'max:100'],
            'items.*.description' => ['required_with:items', 'string', 'max:255'],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1', 'max:1000000'],
            'items.*.unitPrice' => ['required_with:items', 'integer', 'min:0', 'max:999999999999'],
        ];
    }
}
