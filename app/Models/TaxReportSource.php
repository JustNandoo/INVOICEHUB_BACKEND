<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxReportSource extends Model
{
    protected $fillable = [
        'tax_period_report_id', 'tax_ledger_entry_id', 'source_type', 'source_id',
        'amount', 'recognized_at', 'metadata',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(TaxPeriodReport::class, 'tax_period_report_id');
    }

    public function ledgerEntry(): BelongsTo
    {
        return $this->belongsTo(TaxLedgerEntry::class, 'tax_ledger_entry_id');
    }

    protected function casts(): array
    {
        return ['amount' => 'integer', 'recognized_at' => 'immutable_datetime', 'metadata' => 'array'];
    }
}
