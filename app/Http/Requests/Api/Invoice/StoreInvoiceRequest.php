<?php

namespace App\Http\Requests\Api\Invoice;

use App\Models\Invoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customerId' => [
                'nullable',
                'integer',
                Rule::exists('customers', 'id')->where('user_id', $this->user()->id)->whereNull('deleted_at'),
            ],
            'customer' => ['required_without:customerId', 'array'],
            'customer.name' => ['required_without:customerId', 'string', 'max:150'],
            'customer.email' => ['nullable', 'email:rfc', 'max:255'],
            'customer.whatsapp' => ['nullable', 'string', 'max:30'],
            'customer.address' => ['nullable', 'string', 'max:1000'],
            'issuerAddress' => ['nullable', 'string', 'max:1000'],
            'issueDate' => ['required', 'date_format:Y-m-d'],
            'dueDate' => ['required', 'date_format:Y-m-d', 'after_or_equal:issueDate'],
            'status' => ['sometimes', Rule::in([Invoice::STATUS_DRAFT, Invoice::STATUS_UNPAID])],
            'taxRate' => ['sometimes', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
            'discountAmount' => ['sometimes', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'items.*.unitPrice' => ['required', 'integer', 'min:0', 'max:999999999999'],
        ];
    }
}
