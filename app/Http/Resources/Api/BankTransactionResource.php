<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BankTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'bankAccount' => $this->whenLoaded('bankAccount', fn (): array => [
                'id' => $this->bankAccount->id,
                'bankCode' => $this->bankAccount->bank_code,
                'accountNumberMasked' => '•••• '.$this->bankAccount->account_number_last_four,
            ]),
            'externalTransactionId' => $this->external_transaction_id,
            'type' => $this->type,
            'amount' => $this->amount,
            'senderName' => $this->sender_name,
            'description' => $this->description,
            'reference' => $this->reference,
            'transactionAt' => $this->transaction_at?->toIso8601String(),
            'status' => $this->status,
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
