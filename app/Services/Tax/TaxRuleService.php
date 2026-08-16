<?php

namespace App\Services\Tax;

use App\Models\TaxRuleVersion;
use Carbon\CarbonInterface;

class TaxRuleService
{
    public function activeRule(?CarbonInterface $date = null): TaxRuleVersion
    {
        $date ??= now();

        $active = TaxRuleVersion::query()
            ->where('is_active', true)
            ->whereDate('valid_from', '<=', $date)
            ->where(fn ($query) => $query->whereNull('valid_until')->orWhereDate('valid_until', '>=', $date))
            ->orderByDesc('valid_from')
            ->first();

        if ($active) {
            return $active;
        }

        return TaxRuleVersion::query()->firstOrCreate(
            ['code' => 'PP55-2022-UMKM-DEFAULT'],
            [
                'name' => 'Final Income Tax for Eligible MSMEs',
                'regulation' => 'PP 55/2022 and applicable amendments',
                'rate_basis_points' => 50,
                'annual_revenue_limit' => 4_800_000_000,
                'individual_non_taxable_threshold' => 500_000_000,
                'valid_from' => '2022-12-20',
                'configuration' => [
                    'notice' => 'Eligibility and usage period must be reviewed against the latest regulation.',
                ],
                'is_active' => true,
            ],
        );
    }
}
