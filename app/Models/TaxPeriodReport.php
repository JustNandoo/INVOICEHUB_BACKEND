<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaxPeriodReport extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_READY = 'ready';

    public const STATUS_REPORTED = 'reported';

    public const STATUS_REVISION_REQUIRED = 'revision_required';

    protected $fillable = [
        'user_id', 'tax_rule_version_id', 'year', 'month', 'status', 'total_revenue',
        'taxable_revenue', 'pending_revenue', 'paid_invoice_count', 'tax_rate_basis_points',
        'estimated_tax', 'findings_count', 'calculation_metadata', 'calculated_at',
        'finalized_at', 'reported_at', 'reference_number', 'notes', 'locked_hash',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function taxRule(): BelongsTo
    {
        return $this->belongsTo(TaxRuleVersion::class, 'tax_rule_version_id');
    }

    public function sources(): HasMany
    {
        return $this->hasMany(TaxReportSource::class);
    }

    public function findings(): HasMany
    {
        return $this->hasMany(TaxAuditFinding::class);
    }

    public function isLocked(): bool
    {
        return in_array($this->status, [self::STATUS_READY, self::STATUS_REPORTED], true);
    }

    protected function casts(): array
    {
        return [
            'year' => 'integer', 'month' => 'integer', 'total_revenue' => 'integer',
            'taxable_revenue' => 'integer', 'pending_revenue' => 'integer',
            'paid_invoice_count' => 'integer', 'tax_rate_basis_points' => 'integer',
            'estimated_tax' => 'integer', 'findings_count' => 'integer',
            'calculation_metadata' => 'array', 'calculated_at' => 'immutable_datetime',
            'finalized_at' => 'immutable_datetime', 'reported_at' => 'immutable_datetime',
        ];
    }
}
