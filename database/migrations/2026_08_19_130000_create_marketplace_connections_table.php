<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_connections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 30);
            $table->string('shop_id', 100)->nullable();
            $table->string('shop_name', 150)->nullable();
            $table->string('status', 20)->default('pending');

            // Token disimpan terenkripsi, sama seperti nomor rekening bank.
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestampTz('token_expires_at')->nullable();
            $table->json('scopes')->nullable();

            // Dipakai untuk mencocokkan callback OAuth dengan permintaan yang sah.
            $table->string('state_token', 80)->nullable();
            $table->timestampTz('state_expires_at')->nullable();

            $table->boolean('auto_sync')->default(true);
            $table->timestampTz('last_synced_at')->nullable();
            $table->timestampTz('synced_until')->nullable();
            $table->text('last_sync_error')->nullable();
            $table->unsignedInteger('imported_order_count')->default(0);
            $table->timestampsTz();

            $table->unique(['user_id', 'platform', 'shop_id'], 'marketplace_connections_shop_unique');
            $table->index(['user_id', 'status']);
            $table->index('state_token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_connections');
    }
};
