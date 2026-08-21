<?php

namespace App\Http\Resources\Api\Anomaly;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AnomalyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'typeLabel' => $this->type->label(),
            'severity' => $this->severity->value,
            'tone' => $this->severity->tone(),
            'status' => $this->status->value,
            'title' => $this->title,
            'description' => $this->description,
            'amountAtRisk' => $this->amount_at_risk,
            'source' => ['type' => $this->source_type, 'id' => $this->source_id],
            'metadata' => $this->metadata,
            'actionUrl' => $this->action_url ?? $this->deriveActionUrl(),
            'explanation' => $this->explanation === null ? null : [
                'text' => $this->explanation,
                'likelyCause' => $this->likely_cause,
                'preventionTip' => $this->prevention_tip,
                'recommendedAction' => $this->recommended_action?->value,
                'actionLabel' => $this->recommended_action?->label(),
                'aiRunId' => $this->ai_run_id,
                'explainedAt' => $this->explained_at?->toIso8601String(),
            ],
            'detectedAt' => $this->detected_at?->toIso8601String(),
            'resolvedAt' => $this->resolved_at?->toIso8601String(),
            'resolution' => $this->resolution,
        ];
    }
}
