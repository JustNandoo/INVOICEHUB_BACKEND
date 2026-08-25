<?php

namespace App\Models;

use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'customer_code',
        'name',
        'email',
        'whatsapp',
        'whatsapp_normalized',
        'city',
        'address',
        'source',
        'is_active',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function latestInvoice(): HasOne
    {
        // Batasan status harus masuk ke dalam ofMany. Bila ditaruh di luar, subquery
        // memilih invoice terbaru tanpa memandang status (misalnya draft), lalu baris
        // itu tersaring keluar dan relasinya menjadi null.
        return $this->hasOne(Invoice::class)->ofMany(
            ['issue_date' => 'max', 'id' => 'max'],
            fn ($query) => $query->whereIn('status', [Invoice::STATUS_UNPAID, Invoice::STATUS_PAID]),
        );
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
