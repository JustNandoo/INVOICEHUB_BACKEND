<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankTransactionImport extends Model
{
    protected $fillable = [
        'user_id', 'bank_account_id', 'original_filename', 'stored_path', 'status',
        'total_rows', 'imported_rows', 'duplicate_rows', 'failed_rows', 'errors', 'completed_at',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(BankTransaction::class);
    }

    protected function casts(): array
    {
        return [
            'total_rows' => 'integer',
            'imported_rows' => 'integer',
            'duplicate_rows' => 'integer',
            'failed_rows' => 'integer',
            'errors' => 'array',
            'completed_at' => 'immutable_datetime',
        ];
    }
}
