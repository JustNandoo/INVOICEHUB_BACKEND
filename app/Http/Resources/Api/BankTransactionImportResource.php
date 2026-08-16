<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BankTransactionImportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'bankAccountId' => $this->bank_account_id,
            'originalFilename' => $this->original_filename,
            'status' => $this->status,
            'totalRows' => $this->total_rows,
            'importedRows' => $this->imported_rows,
            'duplicateRows' => $this->duplicate_rows,
            'failedRows' => $this->failed_rows,
            'errors' => $this->errors,
            'completedAt' => $this->completed_at?->toIso8601String(),
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
