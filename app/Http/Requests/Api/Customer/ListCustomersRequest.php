<?php

namespace App\Http\Requests\Api\Customer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListCustomersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $defaults = [
            'page' => $this->input('page', 1),
            'perPage' => $this->input('perPage', 5),
            'status' => $this->input('status', 'all'),
            'sortBy' => $this->input('sortBy', 'name'),
            'sortDirection' => $this->input('sortDirection', 'asc'),
        ];
        if ($this->has('hasOutstanding')) {
            $defaults['hasOutstanding'] = $this->boolean('hasOutstanding');
        }
        $this->merge($defaults);
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:150'],
            'status' => ['required', Rule::in(['all', 'active', 'inactive'])],
            'hasOutstanding' => ['sometimes', 'boolean'],
            'source' => ['sometimes', Rule::in(['manual', 'tokopedia', 'shopee', 'other'])],
            'sortBy' => ['required', Rule::in(['name', 'totalTransactionValue', 'totalOutstanding', 'lastInvoiceDate', 'newest'])],
            'sortDirection' => ['required', Rule::in(['asc', 'desc'])],
            'page' => ['required', 'integer', 'min:1'],
            'perPage' => ['required', 'integer', 'between:1,100'],
        ];
    }
}
