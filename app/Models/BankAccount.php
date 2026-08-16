<?php

namespace App\Models;

use Database\Factories\BankAccountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BankAccount extends Model
{
    /** @use HasFactory<BankAccountFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id', 'bank_code', 'bank_name', 'account_holder', 'account_number',
        'account_number_last_four', 'balance', 'connection_status', 'last_synced_at', 'last_sync_error',
        'account_number_fingerprint',
    ];

    protected $hidden = ['account_number', 'account_number_fingerprint'];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(BankTransaction::class);
    }

    public function imports(): HasMany
    {
        return $this->hasMany(BankTransactionImport::class);
    }

    protected function casts(): array
    {
        return [
            'account_number' => 'encrypted',
            'balance' => 'integer',
            'last_synced_at' => 'immutable_datetime',
        ];
    }
}
