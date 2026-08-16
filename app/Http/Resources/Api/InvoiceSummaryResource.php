<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoiceNumber' => $this->number,
            'customer' => [
                'id' => $this->customer_id,
                'name' => $this->customer_name,
                'email' => $this->customer_email,
            ],
            'issueDate' => $this->issue_date?->format('Y-m-d'),
            'dueDate' => $this->due_date?->format('Y-m-d'),
            'status' => $this->effectiveStatus(),
            'storedStatus' => $this->status,
            'totalAmount' => $this->total_amount,
            'paidAmount' => $this->paid_amount,
            'balanceDue' => $this->balance_due,
            'sentAt' => $this->sent_at?->toIso8601String(),
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
