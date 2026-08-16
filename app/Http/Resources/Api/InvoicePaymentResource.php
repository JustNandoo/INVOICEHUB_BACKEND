<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoicePaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'amount' => $this->amount,
            'method' => $this->method,
            'source' => $this->source,
            'reference' => $this->reference,
            'paidAt' => $this->paid_at?->toIso8601String(),
            'notes' => $this->notes,
            'voidedAt' => $this->voided_at?->toIso8601String(),
            'voidReason' => $this->void_reason,
            'recordedAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
