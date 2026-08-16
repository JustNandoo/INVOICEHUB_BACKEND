<?php

namespace App\Http\Resources\Api;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaxReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'period' => ['year' => $this->year, 'month' => $this->month, 'monthName' => Carbon::create($this->year, $this->month)->locale('id')->translatedFormat('F')],
            'monthlySummary' => [
                'totalRevenue' => $this->total_revenue,
                'taxableRevenue' => $this->taxable_revenue,
                'pendingRevenue' => $this->pending_revenue,
                'paidInvoiceCount' => $this->paid_invoice_count,
                'taxRate' => $this->tax_rate_basis_points / 100,
                'estimatedTax' => $this->estimated_tax,
            ],
            'reportStatus' => $this->status,
            'totalFindings' => $this->findings_count,
            'isLocked' => $this->isLocked(),
            'canDownload' => $this->isLocked(),
            'calculatedAt' => $this->calculated_at?->toIso8601String(),
            'finalizedAt' => $this->finalized_at?->toIso8601String(),
            'reportedAt' => $this->reported_at?->toIso8601String(),
            'referenceNumber' => $this->reference_number,
            'notes' => $this->notes,
        ];
    }
}
