<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonthlyRevenueTarget extends Model
{
    protected $fillable = ['user_id', 'year', 'month', 'amount', 'reached_at'];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'amount' => 'integer',
            'reached_at' => 'immutable_datetime',
        ];
    }
}
