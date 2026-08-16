<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxLedgerEntry extends Model
{
    public const STATUS_RECORDED = 'recorded';

    public const STATUS_PENDING = 'pending';

    public const STATUS_EXCLUDED = 'excluded';

    protected $fillable = [
        'user_id', 'source_type', 'source_id', 'entry_type', 'amount', 'status',
        'is_taxable', 'recognized_at', 'description', 'metadata',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'is_taxable' => 'boolean',
            'recognized_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }
}
