<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaxAuditFindingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'severity' => $this->severity,
            'title' => $this->title,
            'description' => $this->description,
            'amount' => $this->amount,
            'status' => $this->status,
            'recommendedAction' => $this->recommended_action,
            'source' => $this->source_type ? ['type' => $this->source_type, 'id' => $this->source_id] : null,
            'resolution' => $this->resolution,
            'notes' => $this->notes,
            'resolvedAt' => $this->resolved_at?->toIso8601String(),
        ];
    }
}
