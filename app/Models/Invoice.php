<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory, SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_UNPAID = 'unpaid';

    public const STATUS_PAID = 'paid';

    public const STATUS_VOID = 'void';

    protected $fillable = [
        'user_id',
        'customer_id',
        'number',
        'status',
        'issuer_name',
        'issuer_email',
        'issuer_address',
        'customer_name',
        'customer_email',
        'customer_whatsapp',
        'customer_address',
        'issue_date',
        'due_date',
        'subtotal',
        'tax_rate_basis_points',
        'tax_amount',
        'discount_amount',
        'total_amount',
        'paid_amount',
        'balance_due',
        'notes',
        'sent_at',
        'viewed_at',
        'paid_at',
        'voided_at',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('position');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class)->orderByDesc('paid_at');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(InvoiceActivity::class)->orderByDesc('occurred_at')->orderByDesc('id');
    }

    public function effectiveStatus(?CarbonInterface $today = null): string
    {
        $today ??= now();

        if ($this->status === self::STATUS_UNPAID && $this->due_date->isBefore($today->startOfDay())) {
            return 'overdue';
        }

        return $this->status;
    }

    protected function casts(): array
    {
        return [
            'issue_date' => 'immutable_date',
            'due_date' => 'immutable_date',
            'sent_at' => 'immutable_datetime',
            'viewed_at' => 'immutable_datetime',
            'paid_at' => 'immutable_datetime',
            'voided_at' => 'immutable_datetime',
            'subtotal' => 'integer',
            'tax_rate_basis_points' => 'integer',
            'tax_amount' => 'integer',
            'discount_amount' => 'integer',
            'total_amount' => 'integer',
            'paid_amount' => 'integer',
            'balance_due' => 'integer',
        ];
    }
}
