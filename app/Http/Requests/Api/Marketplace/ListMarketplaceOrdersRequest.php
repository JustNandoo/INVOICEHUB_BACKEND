<?php

namespace App\Http\Requests\Api\Marketplace;

use App\Enums\Marketplace\MarketplacePlatform;
use App\Models\MarketplaceOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListMarketplaceOrdersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'perPage' => $this->input('perPage', 15),
            'page' => $this->input('page', 1),
        ]);
    }

    public function rules(): array
    {
        return [
            'platform' => ['nullable', Rule::in(MarketplacePlatform::values())],
            'syncStatus' => ['nullable', Rule::in([
                MarketplaceOrder::SYNC_PENDING,
                MarketplaceOrder::SYNC_IMPORTED,
                MarketplaceOrder::SYNC_SKIPPED,
                MarketplaceOrder::SYNC_FAILED,
            ])],
            'perPage' => ['required', 'integer', 'min:1', 'max:100'],
            'page' => ['required', 'integer', 'min:1'],
        ];
    }
}
