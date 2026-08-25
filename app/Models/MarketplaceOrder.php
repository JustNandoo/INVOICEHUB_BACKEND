<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceOrder extends Model
{
    public const SYNC_PENDING = 'pending';

    public const SYNC_IMPORTED = 'imported';

    public const SYNC_SKIPPED = 'skipped';

    public const SYNC_FAILED = 'failed';

    protected $fillable = [
        'user_id', 'marketplace_connection_id', 'external_order_id', 'order_number', 'status',
        'buyer_name', 'buyer_phone', 'buyer_email', 'buyer_city', 'buyer_address',
        'total_amount', 'shipping_fee', 'platform_fee', 'discount_amount', 'items',
        'ordered_at', 'customer_id', 'invoice_id', 'sync_status', 'sync_error', 'raw_payload',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(MarketplaceConnection::class, 'marketplace_connection_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    protected function casts(): array
    {
        return [
            'items' => 'array',
            'raw_payload' => 'array',
            'total_amount' => 'integer',
            'shipping_fee' => 'integer',
            'platform_fee' => 'integer',
            'discount_amount' => 'integer',
            'ordered_at' => 'immutable_datetime',
        ];
    }
}
