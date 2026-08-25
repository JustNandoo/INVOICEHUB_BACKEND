<?php

namespace App\Http\Requests\Api\Marketplace;

use App\Enums\Marketplace\MarketplacePlatform;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConnectMarketplaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'platform' => ['required', Rule::in(MarketplacePlatform::values())],
            'shopId' => ['nullable', 'string', 'max:100'],
        ];
    }
}
