<?php

namespace App\Services\Marketplace\Support;

use Carbon\CarbonImmutable;

/**
 * Bentuk pesanan yang seragam untuk semua platform. Tiap provider menerjemahkan
 * responsnya ke sini, sehingga sisa aplikasi tidak perlu tahu asalnya dari mana.
 */
class MarketplaceOrderData
{
    /**
     * @param  list<array{description: string, quantity: int, unitPrice: int}>  $items
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly string $externalOrderId,
        public readonly ?string $orderNumber,
        public readonly string $status,
        public readonly string $buyerName,
        public readonly ?string $buyerPhone,
        public readonly ?string $buyerEmail,
        public readonly ?string $buyerCity,
        public readonly ?string $buyerAddress,
        public readonly int $totalAmount,
        public readonly int $shippingFee,
        public readonly int $platformFee,
        public readonly int $discountAmount,
        public readonly array $items,
        public readonly CarbonImmutable $orderedAt,
        public readonly array $raw = [],
    ) {}

    /** @return array<string, mixed> */
    public function toAttributes(): array
    {
        return [
            'external_order_id' => $this->externalOrderId,
            'order_number' => $this->orderNumber,
            'status' => $this->status,
            'buyer_name' => $this->buyerName,
            'buyer_phone' => $this->buyerPhone,
            'buyer_email' => $this->buyerEmail,
            'buyer_city' => $this->buyerCity,
            'buyer_address' => $this->buyerAddress,
            'total_amount' => max(0, $this->totalAmount),
            'shipping_fee' => max(0, $this->shippingFee),
            'platform_fee' => max(0, $this->platformFee),
            'discount_amount' => max(0, $this->discountAmount),
            'items' => $this->items,
            'ordered_at' => $this->orderedAt,
            'raw_payload' => $this->raw,
        ];
    }
}
