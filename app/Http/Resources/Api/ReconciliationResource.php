<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReconciliationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'matchedBy' => $this->matched_by,
            'score' => $this->score,
            'appliedAmount' => $this->applied_amount,
            'transaction' => (new BankTransactionResource($this->whenLoaded('bankTransaction')))->resolve($request),
            'invoice' => $this->whenLoaded('invoice', fn (): array => [
                'id' => $this->invoice->id,
                'invoiceNumber' => $this->invoice->number,
                'customerName' => $this->invoice->customer_name,
                'totalAmount' => $this->invoice->total_amount,
                'status' => $this->invoice->effectiveStatus(),
            ]),
            'adjustments' => $this->whenLoaded('adjustments', fn () => $this->adjustments->map(fn ($adjustment): array => [
                'id' => $adjustment->id,
                'type' => $adjustment->type,
                'amount' => $adjustment->amount,
                'description' => $adjustment->description,
            ])->values()),
            'notes' => $this->notes,
            'confirmedAt' => $this->confirmed_at?->toIso8601String(),
            'reversedAt' => $this->reversed_at?->toIso8601String(),
            'reversalReason' => $this->reversal_reason,
        ];
    }
}
