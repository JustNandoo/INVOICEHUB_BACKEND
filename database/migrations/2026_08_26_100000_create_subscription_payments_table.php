<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_plan_id')->constrained()->restrictOnDelete();

            // Dikirim ke Midtrans sebagai order_id, jadi wajib unik seumur hidup akun.
            $table->string('order_id', 60)->unique();
            $table->string('status', 20)->default('pending');
            $table->unsignedBigInteger('amount');
            $table->string('provider', 40)->default('midtrans');
            $table->string('provider_reference', 120)->nullable();
            $table->string('payment_type', 40)->nullable();
            $table->text('snap_token')->nullable();
            $table->timestampTz('paid_at')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->string('failure_reason', 255)->nullable();

            // Payload notifikasi terakhir disimpan utuh sebagai jejak audit pembayaran.
            $table->json('raw_payload')->nullable();
            $table->timestampsTz();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'paid_at']);
            $table->unique(['provider', 'provider_reference']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_payments');
    }
};
