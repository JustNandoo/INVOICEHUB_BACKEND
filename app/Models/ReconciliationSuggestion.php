<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReconciliationSuggestion extends Model
{
    protected $fillable = [
        'bank_transaction_id', 'invoice_id', 'score', 'suggested_applied_amount',
        'difference_amount', 'difference_type', 'reasons', 'status',
        'ai_run_id', 'ai_rank', 'ai_confidence', 'ai_reasons', 'ai_requires_review',
    ];

    public function bankTransaction(): BelongsTo
    {
        return $this->belongsTo(BankTransaction::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function aiRun(): BelongsTo
    {
        return $this->belongsTo(AiRun::class, 'ai_run_id');
    }

    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'suggested_applied_amount' => 'integer',
            'difference_amount' => 'integer',
            'reasons' => 'array',
            'ai_rank' => 'integer',
            'ai_confidence' => 'integer',
            'ai_reasons' => 'array',
            'ai_requires_review' => 'boolean',
        ];
    }
}
