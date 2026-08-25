<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('marketplace_connection_id')->constrained()->cascadeOnDelete();

            $table->string('external_order_id', 120);
            $table->string('order_number', 120)->nullable();
            $table->string('status', 40)->default('unknown');

            $table->string('buyer_name', 150);
            $table->string('buyer_phone', 40)->nullable();
            $table->string('buyer_email', 255)->nullable();
            $table->string('buyer_city', 100)->nullable();
            $table->text('buyer_address')->nullable();

            $table->unsignedBigInteger('total_amount')->default(0);
            $table->unsignedBigInteger('shipping_fee')->default(0);
            $table->unsignedBigInteger('platform_fee')->default(0);
            $table->unsignedBigInteger('discount_amount')->default(0);
            $table->json('items')->nullable();

            $table->timestampTz('ordered_at');

            // Hasil pemetaan ke data InvoiceHub.
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->string('sync_status', 20)->default('pending');
            $table->text('sync_error')->nullable();

            $table->json('raw_payload')->nullable();
            $table->timestampsTz();

            // Sinkronisasi ulang tidak boleh menggandakan pesanan.
            $table->unique(['marketplace_connection_id', 'external_order_id'], 'marketplace_orders_external_unique');
            $table->index(['user_id', 'sync_status']);
            $table->index(['user_id', 'ordered_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_orders');
    }
};
