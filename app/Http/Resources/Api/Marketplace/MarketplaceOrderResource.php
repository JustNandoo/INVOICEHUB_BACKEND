<?php

namespace App\Http\Resources\Api\Marketplace;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MarketplaceOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'platform' => $this->connection?->platform->value,
            'externalOrderId' => $this->external_order_id,
            'orderNumber' => $this->order_number,
            'status' => $this->status,
            'buyer' => [
                'name' => $this->buyer_name,
                'phone' => $this->buyer_phone,
                'city' => $this->buyer_city,
            ],
            'totalAmount' => $this->total_amount,
            'shippingFee' => $this->shipping_fee,
            'platformFee' => $this->platform_fee,
            'orderedAt' => $this->ordered_at?->toIso8601String(),
            'syncStatus' => $this->sync_status,
            'syncError' => $this->sync_error,
            'customerId' => $this->customer_id,
            'invoiceId' => $this->invoice_id,
        ];
    }
}
