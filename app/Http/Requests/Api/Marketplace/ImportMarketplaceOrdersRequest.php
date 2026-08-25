<?php

namespace App\Http\Requests\Api\Marketplace;

use Illuminate\Foundation\Http\FormRequest;

class ImportMarketplaceOrdersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,txt,xls,xlsx', 'max:10240'],
        ];
    }
}
