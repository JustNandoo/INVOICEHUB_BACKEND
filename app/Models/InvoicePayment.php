<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class InvoicePayment extends Model
{
    protected $fillable = [
        'recorded_by', 'amount', 'method', 'source', 'reference', 'paid_at', 'notes', 'voided_at', 'void_reason',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function reconciliation(): HasOne
    {
        return $this->hasOne(Reconciliation::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('voided_at');
    }

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'paid_at' => 'immutable_datetime',
            'voided_at' => 'immutable_datetime',
        ];
    }
}
