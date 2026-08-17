<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeeklyFinancialReport extends Model
{
    public const STATUS_READY = 'ready';

    protected $fillable = [
        'user_id', 'period_start', 'period_end', 'status', 'metrics', 'generated_at',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    protected function casts(): array
    {
        return [
            'period_start' => 'immutable_date',
            'period_end' => 'immutable_date',
            'metrics' => 'array',
            'generated_at' => 'immutable_datetime',
        ];
    }
}
