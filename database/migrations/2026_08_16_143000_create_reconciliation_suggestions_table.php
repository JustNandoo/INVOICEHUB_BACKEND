<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reconciliation_suggestions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bank_transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('score');
            $table->unsignedBigInteger('suggested_applied_amount');
            $table->unsignedBigInteger('difference_amount')->default(0);
            $table->string('difference_type', 30)->nullable();
            $table->json('reasons');
            $table->string('status', 20)->default('pending');
            $table->timestampsTz();

            $table->unique(['bank_transaction_id', 'invoice_id']);
            $table->index(['bank_transaction_id', 'status', 'score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliation_suggestions');
    }
};
