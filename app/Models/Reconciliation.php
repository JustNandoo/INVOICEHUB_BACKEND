<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reconciliation extends Model
{
    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_REVERSED = 'reversed';

    protected $fillable = [
        'user_id', 'bank_transaction_id', 'invoice_id', 'invoice_payment_id', 'confirmed_by',
        'matched_by', 'score', 'applied_amount', 'status', 'notes', 'confirmed_at',
        'reversed_at', 'reversal_reason',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function bankTransaction(): BelongsTo
    {
        return $this->belongsTo(BankTransaction::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(InvoicePayment::class, 'invoice_payment_id');
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(ReconciliationAdjustment::class);
    }

    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'applied_amount' => 'integer',
            'confirmed_at' => 'immutable_datetime',
            'reversed_at' => 'immutable_datetime',
        ];
    }
}
