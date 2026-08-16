<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bank_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bank_transaction_import_id')->nullable()->constrained()->nullOnDelete();
            $table->string('external_transaction_id', 150)->nullable();
            $table->char('fingerprint', 64);
            $table->string('type', 10);
            $table->unsignedBigInteger('amount');
            $table->string('sender_name', 150)->nullable();
            $table->text('description')->nullable();
            $table->string('reference', 150)->nullable();
            $table->timestampTz('transaction_at');
            $table->string('status', 30)->default('unmatched');
            $table->json('raw_payload')->nullable();
            $table->timestampsTz();

            $table->unique(['bank_account_id', 'fingerprint']);
            $table->index(['user_id', 'status', 'transaction_at']);
            $table->index(['bank_account_id', 'transaction_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_transactions');
    }
};
