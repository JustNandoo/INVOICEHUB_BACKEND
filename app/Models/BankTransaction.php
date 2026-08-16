<?php

namespace App\Models;

use Database\Factories\BankTransactionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankTransaction extends Model
{
    /** @use HasFactory<BankTransactionFactory> */
    use HasFactory;

    public const STATUS_UNMATCHED = 'unmatched';

    public const STATUS_NEEDS_CONFIRMATION = 'needs_confirmation';

    public const STATUS_MATCHED = 'matched';

    public const STATUS_IGNORED = 'ignored';

    protected $fillable = [
        'user_id', 'bank_account_id', 'bank_transaction_import_id', 'external_transaction_id',
        'fingerprint', 'type', 'amount', 'sender_name', 'description', 'reference',
        'transaction_at', 'status', 'raw_payload',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(BankTransactionImport::class, 'bank_transaction_import_id');
    }

    public function suggestions(): HasMany
    {
        return $this->hasMany(ReconciliationSuggestion::class);
    }

    public function reconciliations(): HasMany
    {
        return $this->hasMany(Reconciliation::class);
    }

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'transaction_at' => 'immutable_datetime',
            'raw_payload' => 'array',
        ];
    }
}
