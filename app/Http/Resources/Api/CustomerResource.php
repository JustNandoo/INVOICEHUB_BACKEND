<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customerCode' => $this->customer_code,
            'name' => $this->name,
            'email' => $this->email,
            'whatsapp' => $this->whatsapp,
            'city' => $this->city,
            'address' => $this->address,
            'source' => $this->source,
            'isActive' => $this->is_active,
            'metrics' => [
                'totalInvoiceCount' => (int) ($this->total_invoice_count ?? 0),
                'paidInvoiceCount' => (int) ($this->paid_invoice_count ?? 0),
                'unpaidInvoiceCount' => (int) ($this->unpaid_invoice_count ?? 0),
                'overdueInvoiceCount' => (int) ($this->overdue_invoice_count ?? 0),
                'totalTransactionValue' => (int) ($this->total_transaction_value ?? 0),
                'totalPaid' => (int) ($this->total_paid ?? 0),
                'totalOutstanding' => (int) ($this->total_outstanding ?? 0),
            ],
            'lastInvoice' => $this->whenLoaded('latestInvoice', fn () => $this->latestInvoice ? [
                'id' => $this->latestInvoice->id,
                'invoiceNumber' => $this->latestInvoice->number,
                'issueDate' => $this->latestInvoice->issue_date?->format('Y-m-d'),
                'status' => $this->latestInvoice->effectiveStatus(),
                'totalAmount' => $this->latestInvoice->total_amount,
            ] : null),
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
