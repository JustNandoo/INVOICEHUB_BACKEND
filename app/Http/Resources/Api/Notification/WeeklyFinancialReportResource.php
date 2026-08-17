<?php

namespace App\Http\Resources\Api\Notification;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WeeklyFinancialReportResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'periodStart' => $this->period_start->toDateString(),
            'periodEnd' => $this->period_end->toDateString(),
            'status' => $this->status,
            'metrics' => $this->metrics,
            'generatedAt' => $this->generated_at->toIso8601String(),
            'downloadUrl' => url("/api/v1/weekly-reports/{$this->id}/pdf"),
        ];
    }
}
