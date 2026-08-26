<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionPayment extends Model
{
    public const STATUS_PENDING = 'pending';

    /** Menunggu peninjauan manual fraud di dashboard Midtrans. */
    public const STATUS_CHALLENGE = 'challenge';

    public const STATUS_PAID = 'paid';

    public const STATUS_FAILED = 'failed';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_REFUNDED = 'refunded';

    protected $fillable = [
        'user_id', 'subscription_plan_id', 'order_id', 'status', 'amount', 'provider',
        'provider_reference', 'payment_type', 'snap_token', 'paid_at', 'expires_at',
        'failure_reason', 'raw_payload',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    /** Status akhir tidak boleh diubah lagi oleh notifikasi susulan. */
    public function isSettled(): bool
    {
        return in_array($this->status, [self::STATUS_PAID, self::STATUS_REFUNDED], true);
    }

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'paid_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'raw_payload' => 'array',
        ];
    }
}
