<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxpayerProfile extends Model
{
    protected $fillable = [
        'user_id', 'tax_rule_version_id', 'taxpayer_type', 'taxpayer_name', 'business_name',
        'npwp', 'npwp_last_four', 'npwp_fingerprint', 'tax_scheme', 'accounting_method', 'effective_from',
    ];

    protected $hidden = ['npwp', 'npwp_fingerprint'];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function taxRule(): BelongsTo
    {
        return $this->belongsTo(TaxRuleVersion::class, 'tax_rule_version_id');
    }

    protected function casts(): array
    {
        return [
            'npwp' => 'encrypted',
            'effective_from' => 'immutable_date',
        ];
    }
}
