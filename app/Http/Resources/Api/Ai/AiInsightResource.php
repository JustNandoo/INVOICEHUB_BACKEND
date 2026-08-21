<?php

namespace App\Http\Resources\Api\Ai;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AiInsightResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'aiRunId' => $this->ai_run_id,
            'type' => $this->type->value,
            'severity' => $this->severity->value,
            'tone' => $this->severity->tone(),
            'title' => $this->title,
            'summary' => $this->summary,
            'evidence' => $this->evidence,
            'recommendedAction' => $this->recommended_action->value,
            'actionLabel' => $this->recommended_action->label(),
            'actionUrl' => $this->action_url,
            'position' => $this->position,
            'validUntil' => $this->valid_until?->toIso8601String(),
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
