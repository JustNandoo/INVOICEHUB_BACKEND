<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReconciliationSuggestionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice' => [
                'id' => $this->invoice->id,
                'invoiceNumber' => $this->invoice->number,
                'customerName' => $this->invoice->customer_name,
                'totalAmount' => $this->invoice->total_amount,
                'remainingBalance' => $this->invoice->balance_due,
                'issueDate' => $this->invoice->issue_date->format('Y-m-d'),
                'dueDate' => $this->invoice->due_date->format('Y-m-d'),
            ],
            'score' => $this->score,
            'suggestedAppliedAmount' => $this->suggested_applied_amount,
            'differenceAmount' => $this->difference_amount,
            'differenceType' => $this->difference_type,
            'reasons' => $this->reasons,
            'status' => $this->status,
        ];
    }
}
