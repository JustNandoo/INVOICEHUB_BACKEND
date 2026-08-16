<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaxpayerProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'taxpayerType' => $this->taxpayer_type,
            'taxpayerName' => $this->taxpayer_name,
            'businessName' => $this->business_name,
            'npwpMasked' => $this->npwp_last_four ? '•••• •••• •••• '.$this->npwp_last_four : null,
            'taxScheme' => $this->tax_scheme,
            'accountingMethod' => $this->accounting_method,
            'effectiveFrom' => $this->effective_from?->toDateString(),
            'taxRule' => [
                'code' => $this->taxRule->code,
                'name' => $this->taxRule->name,
                'regulation' => $this->taxRule->regulation,
                'taxRate' => $this->taxRule->rate_basis_points / 100,
                'annualRevenueLimit' => $this->taxRule->annual_revenue_limit,
                'individualNonTaxableThreshold' => $this->taxRule->individual_non_taxable_threshold,
                'validFrom' => $this->taxRule->valid_from->toDateString(),
                'validUntil' => $this->taxRule->valid_until?->toDateString(),
            ],
        ];
    }
}
