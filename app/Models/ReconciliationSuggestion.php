<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReconciliationSuggestion extends Model
{
    protected $fillable = [
        'bank_transaction_id', 'invoice_id', 'score', 'suggested_applied_amount',
        'difference_amount', 'difference_type', 'reasons', 'status',
    ];

    public function bankTransaction(): BelongsTo
    {
        return $this->belongsTo(BankTransaction::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'suggested_applied_amount' => 'integer',
            'difference_amount' => 'integer',
            'reasons' => 'array',
        ];
    }
}
