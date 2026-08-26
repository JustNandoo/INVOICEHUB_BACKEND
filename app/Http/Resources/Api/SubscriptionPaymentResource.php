<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionPaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'orderId' => $this->order_id,
            'planCode' => $this->plan?->code,
            'planName' => $this->plan?->name,
            'amount' => $this->amount,
            'status' => $this->status,
            'statusLabel' => $this->statusLabel(),
            'paymentType' => $this->payment_type,
            'paidAt' => $this->paid_at?->toIso8601String(),
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }

    private function statusLabel(): string
    {
        return match ($this->status) {
            'paid' => 'Lunas',
            'pending' => 'Menunggu Pembayaran',
            'challenge' => 'Menunggu Peninjauan',
            'expired' => 'Kedaluwarsa',
            'refunded' => 'Dikembalikan',
            default => 'Gagal',
        };
    }
}
