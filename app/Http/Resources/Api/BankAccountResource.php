<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BankAccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'bankCode' => $this->bank_code,
            'bankName' => $this->bank_name,
            'accountHolder' => $this->account_holder,
            'accountNumberMasked' => '•••• '.$this->account_number_last_four,
            'balance' => $this->balance,
            'connectionStatus' => $this->connection_status,
            'lastSyncedAt' => $this->last_synced_at?->toIso8601String(),
            'lastSyncError' => $this->last_sync_error,
        ];
    }
}
