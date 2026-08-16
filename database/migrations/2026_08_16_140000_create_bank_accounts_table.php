<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('bank_code', 20);
            $table->string('bank_name', 100);
            $table->string('account_holder', 150);
            $table->text('account_number')->nullable();
            $table->string('account_number_last_four', 4);
            $table->unsignedBigInteger('balance')->default(0);
            $table->string('connection_status', 20)->default('connected');
            $table->timestampTz('last_synced_at')->nullable();
            $table->text('last_sync_error')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['user_id', 'connection_status']);
            $table->unique(['user_id', 'bank_code', 'account_number_last_four']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_accounts');
    }
};
