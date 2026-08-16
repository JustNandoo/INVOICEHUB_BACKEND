<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoiceNumber' => $this->number,
            'status' => $this->effectiveStatus(),
            'storedStatus' => $this->status,
            'issuer' => [
                'name' => $this->issuer_name,
                'email' => $this->issuer_email,
                'address' => $this->issuer_address,
            ],
            'customer' => [
                'id' => $this->customer_id,
                'name' => $this->customer_name,
                'email' => $this->customer_email,
                'whatsapp' => $this->customer_whatsapp,
                'address' => $this->customer_address,
            ],
            'issueDate' => $this->issue_date?->format('Y-m-d'),
            'dueDate' => $this->due_date?->format('Y-m-d'),
            'items' => InvoiceItemResource::collection($this->whenLoaded('items')),
            'totals' => [
                'subtotal' => $this->subtotal,
                'taxRate' => $this->tax_rate_basis_points / 100,
                'taxAmount' => $this->tax_amount,
                'discountAmount' => $this->discount_amount,
                'totalAmount' => $this->total_amount,
                'paidAmount' => $this->paid_amount,
                'balanceDue' => $this->balance_due,
            ],
            'notes' => $this->notes,
            'payments' => InvoicePaymentResource::collection($this->whenLoaded('payments')),
            'activities' => InvoiceActivityResource::collection($this->whenLoaded('activities')),
            'sentAt' => $this->sent_at?->toIso8601String(),
            'viewedAt' => $this->viewed_at?->toIso8601String(),
            'paidAt' => $this->paid_at?->toIso8601String(),
            'voidedAt' => $this->voided_at?->toIso8601String(),
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
