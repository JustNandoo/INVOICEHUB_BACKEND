<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxAuditFinding extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_DISMISSED = 'dismissed';

    protected $fillable = [
        'user_id', 'tax_period_report_id', 'year', 'month', 'type', 'severity',
        'title', 'description', 'amount', 'status', 'recommended_action',
        'source_type', 'source_id', 'resolution', 'notes', 'resolved_by',
        'resolved_at', 'metadata',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(TaxPeriodReport::class, 'tax_period_report_id');
    }

    protected function casts(): array
    {
        return [
            'year' => 'integer', 'month' => 'integer', 'amount' => 'integer',
            'resolved_at' => 'immutable_datetime', 'metadata' => 'array',
        ];
    }
}
