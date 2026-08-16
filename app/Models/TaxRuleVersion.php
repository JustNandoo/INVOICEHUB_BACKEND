<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaxRuleVersion extends Model
{
    protected $fillable = [
        'code', 'name', 'regulation', 'rate_basis_points', 'annual_revenue_limit',
        'individual_non_taxable_threshold', 'valid_from', 'valid_until', 'configuration', 'is_active',
    ];

    public function profiles(): HasMany
    {
        return $this->hasMany(TaxpayerProfile::class);
    }

    protected function casts(): array
    {
        return [
            'rate_basis_points' => 'integer',
            'annual_revenue_limit' => 'integer',
            'individual_non_taxable_threshold' => 'integer',
            'valid_from' => 'immutable_date',
            'valid_until' => 'immutable_date',
            'configuration' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
