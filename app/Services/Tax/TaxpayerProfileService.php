<?php

namespace App\Services\Tax;

use App\Models\TaxpayerProfile;
use App\Models\User;

class TaxpayerProfileService
{
    public function __construct(private readonly TaxRuleService $rules) {}

    /** @param array<string, mixed> $data */
    public function update(User $user, array $data): TaxpayerProfile
    {
        $npwp = $data['npwp'] ?? null;
        $profile = TaxpayerProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'tax_rule_version_id' => $this->rules->activeRule()->id,
                'taxpayer_type' => $data['taxpayerType'],
                'taxpayer_name' => trim($data['taxpayerName']),
                'business_name' => trim($data['businessName']),
                'npwp' => $npwp,
                'npwp_last_four' => $npwp ? substr($npwp, -4) : null,
                'npwp_fingerprint' => $npwp ? hash('sha256', $npwp) : null,
                'tax_scheme' => $data['taxScheme'] ?? 'final_umkm',
                'accounting_method' => $data['accountingMethod'] ?? 'cash_basis',
                'effective_from' => $data['effectiveFrom'] ?? today()->toDateString(),
            ],
        );

        return $profile->load('taxRule');
    }
}
